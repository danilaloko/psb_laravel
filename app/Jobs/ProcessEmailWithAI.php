<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\Generation;
use App\Services\YandexAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessEmailWithAI implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Задержки между попытками: 10с, 30с, 60с
    public $timeout = 120; // Таймаут выполнения job - 2 минуты

    protected Email $email;
    protected ?string $indexId;

    public function __construct(Email $email, ?string $indexId = null)
    {
        $this->email = $email;
        $this->indexId = $indexId;
        $this->onQueue('ai-processing'); // Специальная очередь для ИИ задач
    }

    public function handle(YandexAIService $aiService): void
    {
        $startTime = microtime(true);

        // Перезагружаем email для корректной работы после десериализации
        $this->email = Email::find($this->email->id);

        try {
            // Выполняем поиск по Vector Store (если указан индекс)
            $searchResults = $this->performVectorSearch();
            $searchContext = $this->formatSearchResults($searchResults);

            // Получаем модель из конфига
            $modelConfig = config('ai-models')['yandex'][config('ai-models.default_model')];

            // Формируем промпт с учетом результатов поиска
            $prompt = $this->buildPrompt($this->email->content, $searchContext);

            // Отправляем запрос в Yandex AI Studio
            $response = $aiService->generateCompletion($prompt, $modelConfig);

            // Вычисляем время обработки
            $processingTime = round(microtime(true) - $startTime, 3);

            // Парсим ответ от Yandex AI
            $parsedResponse = $this->parseYandexResponse($response);

            // Проверяем на спам - если спам, создаем упрощенный ответ
            if (($parsedResponse['spam_check'] ?? 0) === 1) {
                $parsedResponse = $this->createSpamResponse();
                Log::info("Email {$this->email->id} identified as spam, skipping detailed analysis");
            }

            // Сохраняем результат с информацией о поиске
            $generation = $this->saveGeneration($parsedResponse, $processingTime, $modelConfig, $response, $searchResults);

            Log::info("Email {$this->email->id} processed successfully", [
                'processing_time' => $processingTime,
                'model' => $modelConfig['name'],
                'generation_id' => $generation->id,
                'search_index_used' => $this->indexId,
                'search_results_count' => $searchResults ? count($searchResults['data'] ?? []) : 0
            ]);

        } catch (Throwable $e) {
            Log::error("Failed to process email {$this->email->id}", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e; // Повторная попытка или failure
        }
    }

    public function failed(Throwable $exception): void
    {
        // Обработка окончательного неудачного выполнения
        Log::critical("Email {$this->email->id} processing failed permanently", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    /**
     * Выполнить поиск по Vector Store индексу
     */
    protected function performVectorSearch(): ?array
    {
        Log::info("performVectorSearch called for email {$this->email->id}, indexId: " . ($this->indexId ?? 'null'));

        if (!$this->indexId) {
            Log::info("No search index specified for email {$this->email->id}");
            return null;
        }

        try {
            // Формируем поисковый запрос на основе содержания email
            $searchQuery = $this->buildSearchQuery();

            Log::info("Performing vector search for email {$this->email->id}", [
                'index_id' => $this->indexId,
                'query' => $searchQuery
            ]);

            $searchResults = app(YandexAIService::class)->searchVectorStore($this->indexId, $searchQuery, 5);

            Log::info("Vector search completed for email {$this->email->id}", [
                'results_count' => count($searchResults['data'] ?? [])
            ]);

            return $searchResults;

        } catch (\Exception $e) {
            Log::error("Vector search failed for email {$this->email->id}", [
                'index_id' => $this->indexId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Возвращаем null при ошибке поиска, чтобы продолжить анализ без поиска
            return null;
        }
    }

    /**
     * Сформировать поисковый запрос на основе содержания email
     */
    protected function buildSearchQuery(): string
    {
        // Используем тему и краткое содержание email для формирования запроса
        $query = $this->email->subject;

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
               "Используй эту информацию для более точного анализа письма.\n\n";
    }

    protected function buildPrompt(string $emailContent, string $searchContext = ''): string
    {
        $template = config('ai-models.prompts.email_analysis.user_template');

        // Добавляем результаты поиска если они есть
        $searchSection = $searchContext
            ? "\n\n{$searchContext}"
            : "";

        return str_replace(
            ['{email_content}', '{response_format}'],
            [$emailContent, $searchSection . $this->getResponseFormat()],
            $template
        );
    }

    protected function getResponseFormat(): string
    {
        return json_encode([
            // Проверка на спам и релевантность (0 - все ок, генерировать анализ; 1 - спам/не соответствующее, не генерировать остальные поля)
            'spam_check' => 'integer (0|1) - проверка на спам, ненужную информацию или не соответствующую почте банка',

            // Существующие параметры для обратной совместимости
            'summary' => 'string',
            'priority' => 'high|medium|low',
            'category' => 'complaint|request|information|support',
            'sentiment' => 'positive|neutral|negative',
            'action_required' => 'boolean',
            'suggested_response' => 'string',
            'key_points' => 'array',
            'deadline' => 'ISO datetime or null',

            // Многоуровневая классификация писем
            'classification' => [
                'primary_type' => 'information_request|complaint|regulatory_request|partnership_proposal|approval_request|notification',
                'secondary_type' => 'document_request|service_complaint|supervisory_requirement|business_offer|contract_approval|status_update',
                'business_context' => 'operational|financial|legal|technical|commercial|administrative',
                'communication_channel' => 'formal|official|semi-formal|informal'
            ],

            // Критичные параметры обработки
            'processing_requirements' => [
                'sla_deadline' => 'ISO datetime - calculated based on request type',
                'response_formality_level' => 'high|medium|low - required tone formality',
                'approval_departments' => ['array of department names requiring verification'],
                'legal_risks' => [
                    'risk_level' => 'high|medium|low|none',
                    'risk_factors' => ['contractual_liability', 'regulatory_compliance', 'financial_impact', 'data_privacy'],
                    'recommended_actions' => ['legal_review', 'management_approval', 'documentation_required']
                ],
                'escalation_required' => 'boolean',
                'escalation_level' => 'department_head|executive|legal|none'
            ],

            // Глубокое извлечение ключевой информации
            'content_analysis' => [
                'core_request' => 'string - precise subject of the appeal and sender expectations',
                'contact_information' => [
                    'sender_details' => [
                        'name' => 'string',
                        'position' => 'string',
                        'organization' => 'string',
                        'phone' => 'string',
                        'additional_contacts' => ['array of alternative contacts']
                    ],
                    'mentioned_parties' => ['array of other organizations/persons mentioned']
                ],
                'regulatory_references' => [
                    'laws_and_regulations' => ['array of mentioned legislative norms'],
                    'contract_references' => ['array of contract numbers or references'],
                    'deadline_mentions' => ['array of any mentioned time constraints']
                ],
                'requirements_and_expectations' => [
                    'explicit_requirements' => ['array of clearly stated needs'],
                    'implicit_expectations' => ['array of inferred expectations'],
                    'preferred_outcome' => 'string - desired result from sender perspective',
                    'acceptable_alternatives' => ['array of acceptable solutions']
                ]
            ],

            // Дополнительные параметры анализа
            'metadata_analysis' => [
                'document_requests' => [
                    'document_types' => ['certificates', 'statements', 'confirmations', 'reports'],
                    'urgency_level' => 'high|medium|low',
                    'format_requirements' => ['PDF', 'original', 'certified_copy']
                ],
                'stakeholder_analysis' => [
                    'affected_parties' => ['internal_departments', 'external_partners', 'regulatory_bodies']
                ],
                'compliance_indicators' => [
                    'gdpr_relevant' => 'boolean',
                    'data_processing_required' => 'boolean',
                    'confidentiality_level' => 'public|internal|confidential|strictly_confidential'
                ]
            ],

            // Рекомендации по действиям
            'action_recommendations' => [
                'immediate_actions' => ['array of actions to take within SLA'],
                'follow_up_actions' => ['array of subsequent steps'],
                'preventive_measures' => ['array of measures to prevent similar issues']
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function saveGeneration(array $parsedResponse, float $processingTime, array $modelConfig, array $apiResponse, ?array $searchResults = null): Generation
    {
        // Формируем контекст поиска
        $searchContext = $this->formatSearchResults($searchResults);
        $prompt = $this->buildPrompt($this->email->content, $searchContext);

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
            'email_id' => $this->email->id,
            'type' => 'analysis',
            'prompt' => $prompt,
            'response' => $parsedResponse, // Уже распарсенный JSON
            'processing_time' => $processingTime,
            'status' => 'success',
            'is_spam' => ($parsedResponse['spam_check'] ?? 0) === 1,
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
            ], $searchMetadata ? ['search_metadata' => $searchMetadata] : [])
        ]);
    }

    protected function parseYandexResponse(array $response): array
    {
        // Yandex AI возвращает ответ в формате:
        // {
        //   "alternatives": [
        //     {
        //       "message": {
        //         "text": "```\n{\n  \"summary\": \"...\"\n}\n```"
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

            // Валидируем и дополняем ответ fallback значениями
            return $this->validateAndFillResponse($parsed);
        } catch (\JsonException $e) {
            Log::error('Failed to parse Yandex AI response JSON', [
                'raw_text' => $text,
                'json_text' => $jsonText,
                'error' => $e->getMessage(),
                'json_error_code' => $e->getCode()
            ]);

            // Возвращаем fallback ответ
            return $this->getFallbackResponse();
        }
    }

    protected function validateAndFillResponse(array $parsed): array
    {
        // Получаем базовую структуру с дефолтными значениями
        $fallback = $this->getFallbackResponse();

        // Рекурсивно сливаем массивы, сохраняя существующие значения
        $validated = array_replace_recursive($fallback, $parsed);

        // Валидируем поле spam_check
        if (!isset($validated['spam_check']) || !in_array($validated['spam_check'], [0, 1])) {
            $validated['spam_check'] = 0; // По умолчанию считаем нормальным письмом
            Log::warning('Invalid or missing spam_check value, defaulting to 0', [
                'email_id' => $this->email->id,
                'original_value' => $parsed['spam_check'] ?? null
            ]);
        }

        return $validated;
    }

    protected function getFallbackResponse(): array
    {
        return [
            // Проверка на спам
            'spam_check' => 0, // По умолчанию считаем нормальным письмом

            // Существующие параметры
            'summary' => 'Не удалось проанализировать письмо',
            'priority' => 'medium',
            'category' => 'information',
            'sentiment' => 'neutral',
            'action_required' => true,
            'suggested_response' => 'Требуется ручная обработка',
            'key_points' => ['Анализ не удался'],
            'deadline' => null,

            // Многоуровневая классификация
            'classification' => [
                'primary_type' => 'information_request',
                'secondary_type' => 'document_request',
                'business_context' => 'operational',
                'communication_channel' => 'official'
            ],

            // Параметры обработки
            'processing_requirements' => [
                'sla_deadline' => null,
                'response_formality_level' => 'medium',
                'approval_departments' => [],
                'legal_risks' => [
                    'risk_level' => 'low',
                    'risk_factors' => [],
                    'recommended_actions' => []
                ],
                'escalation_required' => false,
                'escalation_level' => 'none'
            ],

            // Анализ контента
            'content_analysis' => [
                'core_request' => 'Не удалось определить суть запроса',
                'contact_information' => [
                    'sender_details' => [
                        'name' => '',
                        'position' => '',
                        'organization' => '',
                        'phone' => '',
                        'additional_contacts' => []
                    ],
                    'mentioned_parties' => []
                ],
                'regulatory_references' => [
                    'laws_and_regulations' => [],
                    'contract_references' => [],
                    'deadline_mentions' => []
                ],
                'requirements_and_expectations' => [
                    'explicit_requirements' => [],
                    'implicit_expectations' => [],
                    'preferred_outcome' => '',
                    'acceptable_alternatives' => []
                ]
            ],

            // Метаданные анализа
            'metadata_analysis' => [
                'document_requests' => [
                    'document_types' => [],
                    'urgency_level' => 'medium',
                    'format_requirements' => []
                ],
                'stakeholder_analysis' => [
                    'affected_parties' => []
                ],
                'compliance_indicators' => [
                    'gdpr_relevant' => false,
                    'data_processing_required' => false,
                    'confidentiality_level' => 'internal'
                ]
            ],

            // Рекомендации по действиям
            'action_recommendations' => [
                'immediate_actions' => ['Провести ручной анализ'],
                'follow_up_actions' => [],
                'preventive_measures' => []
            ]
        ];
    }

    protected function createSpamResponse(): array
    {
        return [
            // Пометка как спам
            'spam_check' => 1,

            // Минимальные поля для совместимости
            'summary' => 'Письмо помечено как спам или не соответствующее',
            'priority' => 'low',
            'category' => 'information',
            'sentiment' => 'neutral',
            'action_required' => false,
            'suggested_response' => 'Не требует ответа',
            'key_points' => ['Спам или не соответствующее письмо'],
            'deadline' => null,

            // Пустые структуры для остальных полей
            'classification' => [
                'primary_type' => 'information_request',
                'secondary_type' => 'status_update',
                'business_context' => 'operational',
                'communication_channel' => 'informal'
            ],
            'processing_requirements' => [
                'sla_deadline' => null,
                'response_formality_level' => 'low',
                'approval_departments' => [],
                'legal_risks' => [
                    'risk_level' => 'none',
                    'risk_factors' => [],
                    'recommended_actions' => []
                ],
                'escalation_required' => false,
                'escalation_level' => 'none'
            ],
            'content_analysis' => [
                'core_request' => 'Не требует обработки',
                'contact_information' => [
                    'sender_details' => [
                        'name' => '',
                        'position' => '',
                        'organization' => '',
                        'phone' => '',
                        'additional_contacts' => []
                    ],
                    'mentioned_parties' => []
                ],
                'regulatory_references' => [
                    'laws_and_regulations' => [],
                    'contract_references' => [],
                    'deadline_mentions' => []
                ],
                'requirements_and_expectations' => [
                    'explicit_requirements' => [],
                    'implicit_expectations' => [],
                    'preferred_outcome' => '',
                    'acceptable_alternatives' => []
                ]
            ],
            'metadata_analysis' => [
                'document_requests' => [
                    'document_types' => [],
                    'urgency_level' => 'low',
                    'format_requirements' => []
                ],
                'stakeholder_analysis' => [
                    'affected_parties' => []
                ],
                'compliance_indicators' => [
                    'gdpr_relevant' => false,
                    'data_processing_required' => false,
                    'confidentiality_level' => 'public'
                ]
            ],
            'action_recommendations' => [
                'immediate_actions' => ['Отклонить письмо'],
                'follow_up_actions' => [],
                'preventive_measures' => ['Добавить отправителя в фильтр спама']
            ]
        ];
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
