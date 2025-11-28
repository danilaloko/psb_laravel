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

    public function __construct(Thread $thread)
    {
        $this->thread = $thread;
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

            // Получаем аналитику для всех писем в треде
            $analyses = $this->getThreadAnalyses();
            
            // Формируем контекст аналитики
            $analysisContext = $this->getAnalysisContext($analyses);

            // Получаем модель из конфига
            $modelConfig = config('ai-models.yandex.' . config('ai-models.default_model'));

            // Формируем промпт с учетом аналитики
            $threadContext = $this->thread->getThreadContext();
            $prompt = $this->buildPrompt($threadContext, $analysisContext);

            // Отправляем запрос в Yandex AI Studio
            $response = $aiService->generateCompletion($prompt, $modelConfig);

            // Вычисляем время обработки
            $processingTime = round(microtime(true) - $startTime, 3);

            // Парсим ответ от Yandex AI
            $parsedResponse = $this->parseYandexResponse($response);

            // Сохраняем результат с информацией об использованной аналитике
            $generation = $this->saveGeneration($parsedResponse, $processingTime, $modelConfig, $response, $analyses);

            Log::info("Thread {$this->thread->id} reply generated successfully", [
                'processing_time' => $processingTime,
                'model' => $modelConfig['name'],
                'generation_id' => $generation->id,
                'analyses_used' => $analyses->count(),
                'emails_with_analysis' => $analyses->pluck('email_id')->unique()->count()
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

    protected function buildPrompt(string $threadContext, string $analysisContext = ''): string
    {
        $template = config('ai-models.prompts.thread_reply.user_template');

        // Если аналитика отсутствует, используем пустую строку
        $analysisSection = $analysisContext 
            ? "\n\n=== Аналитика писем ===\n{$analysisContext}\n"
            : "\n\nПримечание: Аналитика для писем не доступна. Используй только контекст переписки.\n";

        return str_replace(
            ['{thread_context}', '{analysis_context}', '{response_format}'],
            [$threadContext, $analysisSection, $this->getResponseFormat()],
            $template
        );
    }

    protected function getResponseFormat(): string
    {
        return json_encode([
            'reply' => 'string'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function saveGeneration(array $parsedResponse, float $processingTime, array $modelConfig, array $apiResponse, $analyses = null): Generation
    {
        // Формируем контекст аналитики для сохранения в промпте
        $analysisContext = '';
        if ($analyses && $analyses->isNotEmpty()) {
            $analysisContext = $this->getAnalysisContext($analyses);
        }
        
        $threadContext = $this->thread->getThreadContext();
        $prompt = $this->buildPrompt($threadContext, $analysisContext);

        // Формируем метаданные об использованной аналитике
        $analysisMetadata = [];
        if ($analyses && $analyses->isNotEmpty()) {
            $analysisMetadata = [
                'analyses_used' => $analyses->count(),
                'email_ids_with_analysis' => $analyses->pluck('email_id')->unique()->values()->toArray(),
                'analysis_generation_ids' => $analyses->pluck('id')->values()->toArray()
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
            ], $analysisMetadata ? ['analysis_metadata' => $analysisMetadata] : [])
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
