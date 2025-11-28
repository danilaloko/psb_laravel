<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Models\Thread;
use App\Services\YandexAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateThreadReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Задержки между попытками: 10с, 30с, 60с
    public $timeout = 120; // Таймаут выполнения job - 2 минуты

    protected Thread $thread;
    protected ?string $indexId;

    public function __construct(Thread $thread, ?string $indexId = null)
    {
        $this->thread = $thread;
        $this->indexId = $indexId;
        $this->onQueue('ai-processing'); // Специальная очередь для ИИ задач
    }

    public function handle(YandexAIService $aiService): void
    {
        $startTime = microtime(true);

        // Перезагружаем thread с отношениями для корректной работы после десериализации
        $this->thread = $this->thread->load('emails');

        try {
            // Валидируем thread
            $this->validateThread();

            // Выполняем поиск по Vector Store (если указан индекс)
            $searchResults = $this->performVectorSearch();
            $searchContext = $this->formatSearchResults($searchResults);

            // Получаем аналитику для всех писем в треде
            $analyses = $this->getThreadAnalyses();

            // Формируем контекст аналитики
            $analysisContext = $this->getAnalysisContext($analyses);

            // Получаем модель из конфига
            $modelConfig = config('ai-models.yandex.' . config('ai-models.default_model'));

            // Формируем промпт с учетом аналитики и результатов поиска
            $threadContext = $this->thread->getThreadContext();
            $prompt = $this->buildPrompt($threadContext, $analysisContext, $searchContext);

            // Отправляем запрос в Yandex AI Studio
            $response = $aiService->generateCompletion($prompt, $modelConfig);

            // Вычисляем время обработки
            $processingTime = round(microtime(true) - $startTime, 3);

            // Парсим ответ от Yandex AI
            $parsedResponse = $this->parseYandexResponse($response);

            // Сохраняем результат с информацией об использованной аналитике и поиске
            $generation = $this->saveGeneration($parsedResponse, $processingTime, $modelConfig, $response, $analyses, $searchResults);

            Log::info("Thread {$this->thread->id} reply generated successfully", [
                'processing_time' => $processingTime,
                'model' => $modelConfig['name'],
                'generation_id' => $generation->id,
                'analyses_used' => $analyses->count(),
                'emails_with_analysis' => $analyses->pluck('email_id')->unique()->count(),
                'search_index_used' => $this->indexId,
                'search_results_count' => $searchResults ? count($searchResults) : 0
            ]);

        } catch (Throwable $e) {
            Log::error("Failed to generate reply for thread {$this->thread->id}", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e; // Повторная попытка или failure
        }
    }

    public function failed(Throwable $exception): void
    {
        // Обработка окончательного неудачного выполнения
        Log::critical("Thread {$this->thread->id} reply generation failed permanently", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    protected function validateThread(): void
    {
        if (!$this->thread->exists()) {
            throw new \InvalidArgumentException("Thread does not exist");
        }

        // Используем уже загруженные emails если они есть
        $emailsCount = $this->thread->relationLoaded('emails')
            ? $this->thread->emails->count()
            : $this->thread->emails()->count();

        if ($emailsCount === 0) {
            throw new \InvalidArgumentException("Thread must contain at least one email");
        }
    }

    /**
     * Выполнить поиск по Vector Store индексу
     */
    protected function performVectorSearch(): ?array
    {
        if (!$this->indexId) {
            Log::info("No search index specified for thread {$this->thread->id}");
            return null;
        }

        try {
            // Формируем поисковый запрос на основе контекста треда
            $searchQuery = $this->buildSearchQuery();

            Log::info("Performing vector search for thread {$this->thread->id}", [
                'index_id' => $this->indexId,
                'query' => $searchQuery
            ]);

            $searchResults = app(YandexAIService::class)->searchVectorStore($this->indexId, $searchQuery, 5);

            Log::info("Vector search completed for thread {$this->thread->id}", [
                'results_count' => count($searchResults)
            ]);

            return $searchResults;

        } catch (\Exception $e) {
            Log::error("Vector search failed for thread {$this->thread->id}", [
                'index_id' => $this->indexId,
                'error' => $e->getMessage()
            ]);

            // Возвращаем null при ошибке поиска, чтобы продолжить генерацию без поиска
            return null;
        }
    }

    /**
     * Сформировать поисковый запрос на основе контекста треда
     */
    protected function buildSearchQuery(): string
    {
        // Берем последнее письмо в треде как основу для поиска
        $latestEmail = $this->thread->emails->sortByDesc('received_at')->first();

        if (!$latestEmail) {
            return '';
        }

        // Используем тему и краткое содержание для формирования запроса
        $query = $latestEmail->subject;

        // Если есть анализ последнего письма, используем ключевые моменты
        $analysis = Generation::where('email_id', $latestEmail->id)
            ->analyses()
            ->successful()
            ->latest()
            ->first();

        if ($analysis && isset($analysis->response['summary'])) {
            $query = $analysis->response['summary'] . ' ' . $query;
        }

        // Ограничиваем длину запроса
        return substr($query, 0, 200);
    }

    /**
     * Форматировать результаты поиска для включения в промпт
     */
    protected function formatSearchResults(?array $searchResults): string
    {
        if (!$searchResults || !isset($searchResults['data']) || empty($searchResults['data'])) {
            return '';
        }

        $formattedResults = [];

        foreach ($searchResults['data'] as $result) {
            $content = $result['content'] ?? [];

            if (empty($content) || !isset($content[0]['text'])) {
                continue;
            }

            $filename = $result['filename'] ?? 'неизвестный файл';
            $score = $result['score'] ?? 0;
            $text = $content[0]['text'];

            // Обрабатываем UTF-8 символы
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            $filename = mb_convert_encoding($filename, 'UTF-8', 'UTF-8');

            // Ограничиваем длину текста для каждого результата
            $truncatedText = mb_strlen($text) > 1000 ? mb_substr($text, 0, 1000) . '...' : $text;

            $formattedResults[] = "=== Результат поиска ===\n" .
                                "Файл: {$filename}\n" .
                                "Релевантность: " . round($score * 100, 2) . "%\n\n" .
                                "Содержание:\n{$truncatedText}";
        }

        if (empty($formattedResults)) {
            return '';
        }

        return "=== Результаты поиска по базе знаний ===\n\n" .
               implode("\n\n---\n\n", $formattedResults) . "\n\n" .
               "Используй эту информацию для формирования более точного и информативного ответа.\n\n";
    }

    protected function getThreadAnalyses()
    {
        // Получаем все успешные аналитики для писем в треде
        $emailIds = $this->thread->emails->pluck('id')->toArray();
        
        return Generation::whereIn('email_id', $emailIds)
            ->analyses()
            ->successful()
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('email_id')
            ->map(function ($group) {
                // Берем последнюю аналитику для каждого письма
                return $group->first();
            });
    }

    protected function getAnalysisContext($analyses): string
    {
        if ($analyses->isEmpty()) {
            Log::info("No analyses found for thread {$this->thread->id}");
            return '';
        }

        $contextParts = [];
        $emailsWithAnalysis = 0;
        $emailsWithoutAnalysis = 0;

        // Сортируем письма по дате получения
        $emails = $this->thread->emails->sortBy('received_at');

        foreach ($emails as $email) {
            $analysis = $analyses->get($email->id);
            
            if (!$analysis || !isset($analysis->response)) {
                $emailsWithoutAnalysis++;
                continue;
            }

            $emailsWithAnalysis++;
            $response = $analysis->response;

            $analysisText = "=== Аналитика для письма: {$email->subject} ===\n";
            $analysisText .= "Дата получения: " . ($email->received_at?->format('d.m.Y H:i') ?? 'неизвестно') . "\n\n";

            // Краткое содержание
            if (!empty($response['summary'])) {
                $analysisText .= "Краткое содержание: {$response['summary']}\n";
            }

            // Приоритет
            if (!empty($response['priority'])) {
                $priorityMap = ['high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий'];
                $priority = $priorityMap[$response['priority']] ?? $response['priority'];
                $analysisText .= "Приоритет: {$priority}\n";
            }

            // Классификация
            if (!empty($response['classification'])) {
                $classification = $response['classification'];
                $analysisText .= "Тип запроса: " . ($classification['primary_type'] ?? 'не определен') . "\n";
                if (!empty($classification['secondary_type'])) {
                    $analysisText .= "Подтип: {$classification['secondary_type']}\n";
                }
                if (!empty($classification['business_context'])) {
                    $analysisText .= "Бизнес-контекст: {$classification['business_context']}\n";
                }
            }

            // Суть запроса
            if (!empty($response['content_analysis']['core_request'])) {
                $analysisText .= "\nСуть запроса: {$response['content_analysis']['core_request']}\n";
            }

            // Требования к обработке
            if (!empty($response['processing_requirements'])) {
                $requirements = $response['processing_requirements'];
                if (!empty($requirements['response_formality_level'])) {
                    $formalityMap = ['high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий'];
                    $formality = $formalityMap[$requirements['response_formality_level']] ?? $requirements['response_formality_level'];
                    $analysisText .= "Уровень формальности ответа: {$formality}\n";
                }
                if (!empty($requirements['sla_deadline'])) {
                    $analysisText .= "SLA дедлайн: {$requirements['sla_deadline']}\n";
                }
            }

            // Юридические риски
            if (!empty($response['processing_requirements']['legal_risks'])) {
                $legalRisks = $response['processing_requirements']['legal_risks'];
                if (!empty($legalRisks['risk_level']) && $legalRisks['risk_level'] !== 'none') {
                    $riskMap = ['high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий'];
                    $risk = $riskMap[$legalRisks['risk_level']] ?? $legalRisks['risk_level'];
                    $analysisText .= "Уровень юридических рисков: {$risk}\n";
                    if (!empty($legalRisks['risk_factors']) && is_array($legalRisks['risk_factors'])) {
                        $analysisText .= "Факторы риска: " . implode(', ', $legalRisks['risk_factors']) . "\n";
                    }
                }
            }

            // Предложенный ответ (если есть)
            if (!empty($response['suggested_response'])) {
                $analysisText .= "\nПредложенный ответ (рекомендация): {$response['suggested_response']}\n";
            }

            // Ключевые моменты
            if (!empty($response['key_points']) && is_array($response['key_points'])) {
                $analysisText .= "\nКлючевые моменты:\n";
                foreach ($response['key_points'] as $point) {
                    $analysisText .= "- {$point}\n";
                }
            }

            // Рекомендации по действиям
            if (!empty($response['action_recommendations']['immediate_actions']) && is_array($response['action_recommendations']['immediate_actions'])) {
                $analysisText .= "\nРекомендуемые действия:\n";
                foreach ($response['action_recommendations']['immediate_actions'] as $action) {
                    $analysisText .= "- {$action}\n";
                }
            }

            $contextParts[] = $analysisText;
        }

        Log::info("Analysis context built for thread {$this->thread->id}", [
            'emails_with_analysis' => $emailsWithAnalysis,
            'emails_without_analysis' => $emailsWithoutAnalysis,
            'total_emails' => $emails->count(),
            'context_length' => strlen(implode("\n\n", $contextParts))
        ]);

        return implode("\n\n", $contextParts);
    }

    protected function buildPrompt(string $threadContext, string $analysisContext = '', string $searchContext = ''): string
    {
        $template = config('ai-models.prompts.thread_reply.user_template');

        // Если аналитика отсутствует, используем пустую строку
        $analysisSection = $analysisContext
            ? "\n\n=== Аналитика писем ===\n{$analysisContext}\n"
            : "\n\nПримечание: Аналитика для писем не доступна. Используй только контекст переписки.\n";

        // Добавляем результаты поиска если они есть
        $searchSection = $searchContext
            ? "\n\n{$searchContext}"
            : "";

        return str_replace(
            ['{thread_context}', '{analysis_context}', '{response_format}'],
            [$threadContext, $analysisSection . $searchSection, $this->getResponseFormat()],
            $template
        );
    }

    protected function getResponseFormat(): string
    {
        return json_encode([
            'reply' => 'string'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function saveGeneration(array $parsedResponse, float $processingTime, array $modelConfig, array $apiResponse, $analyses = null, ?array $searchResults = null): Generation
    {
        // Формируем контекст аналитики для сохранения в промпте
        $analysisContext = '';
        if ($analyses && $analyses->isNotEmpty()) {
            $analysisContext = $this->getAnalysisContext($analyses);
        }

        // Формируем контекст поиска
        $searchContext = $this->formatSearchResults($searchResults);

        $threadContext = $this->thread->getThreadContext();
        $prompt = $this->buildPrompt($threadContext, $analysisContext, $searchContext);

        // Формируем метаданные об использованной аналитике
        $analysisMetadata = [];
        if ($analyses && $analyses->isNotEmpty()) {
            $analysisMetadata = [
                'analyses_used' => $analyses->count(),
                'email_ids_with_analysis' => $analyses->pluck('email_id')->unique()->values()->toArray(),
                'analysis_generation_ids' => $analyses->pluck('id')->values()->toArray()
            ];
        }

        // Формируем метаданные о поиске
        $searchMetadata = [];
        if ($this->indexId && $searchResults !== null && isset($searchResults['data'])) {
            $searchMetadata = [
                'vector_search' => [
                    'index_id' => $this->indexId,
                    'query' => $this->buildSearchQuery(),
                    'results_count' => count($searchResults['data']),
                    'results' => array_map(function ($result) {
                        return [
                            'filename' => $result['filename'] ?? null,
                            'score' => $result['score'] ?? 0,
                            'file_id' => $result['file_id'] ?? null,
                            'content_length' => isset($result['content'][0]['text']) ? strlen($result['content'][0]['text']) : 0
                        ];
                    }, $searchResults['data'])
                ]
            ];
        }

        return Generation::create([
            'thread_id' => $this->thread->id,
            'type' => 'reply',
            'prompt' => $prompt,
            'response' => $parsedResponse, // Уже распарсенный JSON
            'processing_time' => $processingTime,
            'status' => 'success',
            'metadata' => array_merge([
                'model' => [
                    'name' => $modelConfig['model'],
                    'version' => $modelConfig['version'],
                    'endpoint' => $modelConfig['endpoint']
                ],
                'tokens' => [
                    'input' => (int)($apiResponse['usage']['inputTextTokens'] ?? 0),
                    'output' => (int)($apiResponse['usage']['completionTokens'] ?? 0),
                    'total' => (int)($apiResponse['usage']['totalTokens'] ?? 0)
                ],
                'cost' => $this->calculateCost([
                    'input_tokens' => (int)($apiResponse['usage']['inputTextTokens'] ?? 0),
                    'output_tokens' => (int)($apiResponse['usage']['completionTokens'] ?? 0)
                ], $modelConfig),
                'request' => [
                    'temperature' => $modelConfig['temperature'],
                    'max_tokens' => $modelConfig['max_tokens']
                ],
                'api_response' => [
                    'request_id' => $apiResponse['request_id'] ?? null,
                    'model_version' => $apiResponse['modelVersion'] ?? null,
                    'finish_reason' => $apiResponse['alternatives'][0]['status'] ?? null
                ]
            ], array_merge(
                $analysisMetadata ? ['analysis_metadata' => $analysisMetadata] : [],
                $searchMetadata ? ['search_metadata' => $searchMetadata] : []
            ))
        ]);
    }

    protected function parseYandexResponse(array $response): array
    {
        // Yandex AI возвращает ответ в формате:
        // {
        //   "alternatives": [
        //     {
        //       "message": {
        //         "text": "```\n{\n  \"reply\": \"...\"\n}\n```"
        //       }
        //     }
        //   ],
        //   "usage": {...}
        // }

        $text = $response['alternatives'][0]['message']['text'] ?? '';

        Log::info('Yandex AI raw response text', [
            'text_length' => strlen($text),
            'text_preview' => substr($text, 0, 200) . '...'
        ]);

        // Убираем markdown код блоки и парсим JSON
        $jsonText = trim($text, "```\n");

        Log::info('Yandex AI JSON text after trim', [
            'json_length' => strlen($jsonText),
            'json_preview' => substr($jsonText, 0, 200) . '...'
        ]);

        try {
            $parsed = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);
            Log::info('Successfully parsed Yandex AI response', ['parsed_keys' => array_keys($parsed)]);
            return $parsed;
        } catch (\JsonException $e) {
            Log::error('Failed to parse Yandex AI response JSON', [
                'raw_text' => $text,
                'json_text' => $jsonText,
                'error' => $e->getMessage(),
                'json_error_code' => $e->getCode()
            ]);

            // Возвращаем fallback ответ
            return [
                'reply' => 'Не удалось сгенерировать ответ. Требуется ручная обработка.'
            ];
        }
    }

    protected function calculateCost(array $usage, array $modelConfig): array
    {
        // Пример расчета стоимости (нужно уточнить реальные тарифы Yandex Cloud)
        $inputRate = 0.000002; // руб/токен
        $outputRate = 0.000004; // руб/токен

        $inputCost = ($usage['input_tokens'] ?? 0) * $inputRate;
        $outputCost = ($usage['output_tokens'] ?? 0) * $outputRate;
        $totalCost = $inputCost + $outputCost;

        return [
            'amount' => round($totalCost, 6),
            'currency' => 'RUB',
            'input_cost' => round($inputCost, 6),
            'output_cost' => round($outputCost, 6),
            'rates' => [
                'input_per_token' => $inputRate,
                'output_per_token' => $outputRate
            ]
        ];
    }

}
