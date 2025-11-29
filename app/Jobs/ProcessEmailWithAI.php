<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\Task;
use App\Models\Thread;
use App\Models\User;
use App\Models\Email;
use App\Models\Generation;
use App\Services\YandexAIService;
use Carbon\Carbon;
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
        
        // Убеждаемся, что email имеет thread_id (fallback если его нет)
        if (!$this->email->thread_id) {
            $thread = Thread::firstOrCreate([
                'title' => $this->email->subject ?: 'Без темы'
            ]);
            $this->email->update(['thread_id' => $thread->id]);
            Log::warning("Email {$this->email->id} had no thread_id, created thread {$thread->id}");
        }

        try {
            // Выполняем поиск по Vector Store (если указан индекс)
            $searchResults = $this->performVectorSearch();
            $searchContext = $this->formatSearchResults($searchResults);

            // Получаем модель из конфига
            $defaultModel = config('ai-models.default_model') ?: 'gpt-5.1-pro';
            $modelConfig = config('ai-models')['yandex'][$defaultModel];

            if (!$modelConfig) {
                throw new \Exception("AI model configuration not found for: {$defaultModel}");
            }

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

            // Создаем задачи на основе анализа
            $createdTasks = $this->createTasksFromAnalysis($generation, $parsedResponse);

            // Обновляем метаданные generation
            $generation->update([
                'metadata' => array_merge($generation->metadata ?? [], [
                    'tasks_created' => true,
                    'created_tasks_count' => count($createdTasks),
                    'created_tasks_ids' => $createdTasks
                ])
            ]);

            Log::info("Email {$this->email->id} processed successfully", [
                'processing_time' => $processingTime,
                'model' => $modelConfig['name'],
                'generation_id' => $generation->id,
                'search_index_used' => $this->indexId,
                'search_results_count' => $searchResults ? count($searchResults['data'] ?? []) : 0,
                'tasks_created_count' => count($createdTasks),
                'tasks_created_ids' => $createdTasks
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

        // Получаем список департаментов
        $departments = $this->getActiveDepartments();
        $departmentsText = "";
        foreach ($departments as $code => $name) {
            $departmentsText .= "- {$code}: {$name}\n";
        }

        return str_replace(
            ['{email_content}', '{departments}', '{response_format}'],
            [$emailContent, $departmentsText, $searchSection . $this->getResponseFormat()],
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
            'category' => 'complaint|request|information|support',
            'sentiment' => 'positive|neutral|negative',
            'action_required' => 'boolean',
            'suggested_response' => 'string',
            'key_points' => 'array',
            'deadline_hours' => 'integer or null - количество часов на выполнение задачи (например, 24 для суток, 72 для трех дней)',

            // Новые поля для задач
            'task_title' => 'string - краткое название задачи (3-7 слов)',
            'department' => 'string - код департамента из списка: support, legal, credits, complaints, general',
            'task_priority' => 'urgent|high|medium|low - приоритет задачи',

            // Многоуровневая классификация писем
            'classification' => [
                'primary_type' => 'information_request|complaint|regulatory_request|partnership_proposal|approval_request|notification',
                'secondary_type' => 'document_request|service_complaint|supervisory_requirement|business_offer|contract_approval|status_update',
                'business_context' => 'operational|financial|legal|technical|commercial|administrative',
                'communication_channel' => 'formal|official|semi-formal|informal'
            ],

            // Критичные параметры обработки
            'processing_requirements' => [
                'sla_deadline_hours' => 'integer or null - количество часов на выполнение задачи согласно SLA (например, 2 для срочных, 24 для обычных)',
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

    /**
     * Получить список активных департаментов для передачи в промпт
     */
    protected function getActiveDepartments(): array
    {
        return Department::active()
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();
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

        // Перезагружаем email из БД перед созданием Generation для гарантии актуальных данных
        $this->email = Email::find($this->email->id);
        
        // Убеждаемся, что у email есть thread_id
        $threadId = $this->email->thread_id;
        
        Log::debug("Creating Generation for email {$this->email->id}", [
            'email_thread_id' => $threadId,
            'email_thread_id_type' => gettype($threadId),
            'email_thread_id_empty' => empty($threadId),
            'email_thread_id_isset' => isset($threadId)
        ]);
        
        if (!$threadId) {
            $thread = Thread::firstOrCreate([
                'title' => $this->email->subject ?: 'Без темы'
            ]);
            $this->email->update(['thread_id' => $thread->id]);
            $threadId = $thread->id;
            Log::warning("Email {$this->email->id} had no thread_id when creating Generation, created thread {$threadId}");
        }
        
        // Дополнительная проверка - если threadId все еще null, создаем thread
        if (!$threadId) {
            $thread = Thread::create([
                'title' => $this->email->subject ?: 'Без темы'
            ]);
            $threadId = $thread->id;
            $this->email->update(['thread_id' => $threadId]);
            Log::error("CRITICAL: Email {$this->email->id} thread_id was null, forced thread creation {$threadId}");
        }
        
        // Финальная проверка перед созданием
        $threadId = (int) $threadId; // Явное приведение к int
        if ($threadId <= 0) {
            throw new \Exception("Cannot create Generation without valid thread_id for email {$this->email->id}. threadId: " . var_export($threadId, true));
        }
        
        Log::debug("Creating Generation with thread_id", [
            'email_id' => $this->email->id,
            'thread_id' => $threadId,
            'thread_id_type' => gettype($threadId)
        ]);

        $generation = Generation::create([
            'email_id' => $this->email->id,
            'thread_id' => $threadId, // Всегда устанавливаем thread_id
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
        
        // Проверяем что thread_id действительно сохранился
        $generation->refresh();
        if (!$generation->thread_id) {
            Log::error("CRITICAL: Generation {$generation->id} created without thread_id!", [
                'email_id' => $this->email->id,
                'expected_thread_id' => $threadId,
                'generation_thread_id' => $generation->thread_id
            ]);
            // Принудительно обновляем
            $generation->update(['thread_id' => $threadId]);
            $generation->refresh();
        }
        
        return $generation;
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

        // Валидируем поле department
        $validDepartments = array_keys($this->getActiveDepartments());
        if (!isset($validated['department']) || !in_array($validated['department'], $validDepartments)) {
            $validated['department'] = 'general'; // По умолчанию общий департамент
            Log::warning('Invalid or missing department value, defaulting to "general"', [
                'email_id' => $this->email->id,
                'original_value' => $parsed['department'] ?? null,
                'valid_departments' => $validDepartments
            ]);
        }

        // Валидируем поле task_title
        if (!isset($validated['task_title']) || !is_string($validated['task_title']) || strlen($validated['task_title']) < 3) {
            $validated['task_title'] = 'Анализ входящего письма'; // По умолчанию
            Log::warning('Invalid or missing task_title value, using default', [
                'email_id' => $this->email->id,
                'original_value' => $parsed['task_title'] ?? null
            ]);
        }

        // Валидируем поле task_priority
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (!isset($validated['task_priority']) || !in_array($validated['task_priority'], $validPriorities)) {
            $validated['task_priority'] = 'medium'; // По умолчанию средний приоритет
            Log::warning('Invalid or missing task_priority value, defaulting to "medium"', [
                'email_id' => $this->email->id,
                'original_value' => $parsed['task_priority'] ?? null
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
            'category' => 'information',
            'sentiment' => 'neutral',
            'action_required' => true,
            'suggested_response' => 'Требуется ручная обработка',
            'key_points' => ['Анализ не удался'],
            'deadline_hours' => null,

            // Новые поля для задач
            'task_title' => 'Анализ входящего письма',
            'department' => 'general',
            'task_priority' => 'medium',

            // Многоуровневая классификация
            'classification' => [
                'primary_type' => 'information_request',
                'secondary_type' => 'document_request',
                'business_context' => 'operational',
                'communication_channel' => 'official'
            ],

            // Параметры обработки
            'processing_requirements' => [
                'sla_deadline_hours' => null,
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
            'category' => 'information',
            'sentiment' => 'neutral',
            'action_required' => false,
            'suggested_response' => 'Не требует ответа',
            'key_points' => ['Спам или не соответствующее письмо'],
            'deadline_hours' => null,

            // Новые поля для задач (для спама - минимальные значения)
            'task_title' => 'Спам или нерелевантное письмо',
            'department' => 'general',
            'task_priority' => 'low',

            // Пустые структуры для остальных полей
            'classification' => [
                'primary_type' => 'information_request',
                'secondary_type' => 'status_update',
                'business_context' => 'operational',
                'communication_channel' => 'informal'
            ],
            'processing_requirements' => [
                'sla_deadline_hours' => null,
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

    // === МЕТОДЫ СОЗДАНИЯ ЗАДАЧ ===

    /**
     * Получить thread_id из generation или email (fallback)
     */
    protected function getThreadId(Generation $generation): ?int
    {
        $threadId = $generation->thread_id;
        if (!$threadId) {
            $email = Email::find($generation->email_id);
            $threadId = $email?->thread_id;
            
            // Если нашли thread_id в email, обновляем generation
            if ($threadId) {
                $generation->update(['thread_id' => $threadId]);
            } else {
                // Fallback: создаем thread если его нет ни у generation, ни у email
                $thread = Thread::firstOrCreate([
                    'title' => $email?->subject ?: 'Без темы'
                ]);
                $threadId = $thread->id;
                
                // Обновляем email и generation
                if ($email) {
                    $email->update(['thread_id' => $threadId]);
                }
                $generation->update(['thread_id' => $threadId]);
                
                Log::warning("Created thread {$threadId} for Generation {$generation->id} as fallback");
            }
        }
        return $threadId;
    }

    protected function createTasksFromAnalysis(Generation $generation, array $analysis): array
    {
        // Проверяем условия создания задач
        if ($this->shouldSkipTaskCreation($analysis)) {
            $this->archiveEmail($analysis, $generation);
            return [];
        }

        // Создаем задачи
        $createdTaskIds = [];

        // Определяем executor_id
        $executorId = $this->findBestExecutor($analysis['department'] ?? 'general');

        // Создаем основную задачу
        $mainTask = $this->createMainTask($analysis, $executorId, $generation);
        $createdTaskIds[] = $mainTask->id;

        // Создаем дополнительные задачи
        $followUpTasks = $this->createFollowUpTasks($analysis, $executorId, $generation);
        $createdTaskIds = array_merge($createdTaskIds, $followUpTasks);

        // Создаем задачи эскалации если нужно
        if ($analysis['processing_requirements']['escalation_required'] ?? false) {
            $escalationTask = $this->createEscalationTask($analysis, $generation);
            $createdTaskIds[] = $escalationTask->id;
        }

        // Создаем задачи по рискам если нужно
        if (($analysis['processing_requirements']['legal_risks']['risk_level'] ?? 'none') !== 'none') {
            $riskTask = $this->createRiskTask($analysis, $generation);
            $createdTaskIds[] = $riskTask->id;
        }

        // Создаем задачи по одобрениям
        $approvalTasks = $this->createApprovalTasks($analysis, $generation);
        $createdTaskIds = array_merge($createdTaskIds, $approvalTasks);

        return $createdTaskIds;
    }

    protected function shouldSkipTaskCreation(array $analysis): bool
    {
        // Проверяем на спам
        if (($analysis['spam_check'] ?? 0) === 1) {
            return true;
        }

        // Проверяем необходимость действий
        if (($analysis['action_required'] ?? false) === false) {
            return true;
        }

        return false;
    }

    protected function findBestExecutor(string $departmentCode): ?int
    {
        // Ищем самого незагруженного пользователя в департаменте
        $user = User::select('users.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('tasks', function($join) {
                $join->on('users.id', '=', 'tasks.executor_id')
                     ->whereIn('tasks.status', ['new', 'in_progress']);
            })
            ->where('departments.code', $departmentCode)
            ->where('users.is_active', true)
            ->groupBy('users.id')
            ->orderByRaw('COUNT(tasks.id) ASC, users.last_task_assigned_at ASC')
            ->first();

        if ($user) {
            // Обновляем время последнего назначения
            User::where('id', $user->id)->update([
                'last_task_assigned_at' => now()
            ]);

            return $user->id;
        }

        return null;
    }

    protected function createMainTask(array $analysis, ?int $executorId, Generation $generation): Task
    {
        $dueDate = $this->parseDueDate($analysis);

        return Task::create([
            'title' => $analysis['task_title'] ?? 'Задача по обработке письма',
            'content' => $this->buildTaskContent($analysis),
            'status' => 'new',
            'priority' => $this->mapPriority($analysis['task_priority'] ?? 'medium'),
            'thread_id' => $this->getThreadId($generation),
            'executor_id' => $executorId,
            'creator_id' => 1, // Системный пользователь
            'due_date' => $dueDate,
            'metadata' => [
                'generation_id' => $generation->id,
                'email_id' => $generation->email_id,
                'analysis_type' => 'main_task',
                'department' => $analysis['department'] ?? 'general'
            ]
        ]);
    }

    protected function createFollowUpTasks(array $analysis, ?int $executorId, Generation $generation): array
    {
        $taskIds = [];
        $followUpActions = $analysis['action_recommendations']['follow_up_actions'] ?? [];

        foreach ($followUpActions as $action) {
            $task = Task::create([
                'title' => $action,
                'content' => "Follow-up действие: {$action}\n\nКонтекст: " . ($analysis['summary'] ?? ''),
                'status' => 'new',
                'priority' => 'medium',
                'thread_id' => $this->getThreadId($generation),
                'executor_id' => $executorId,
                'creator_id' => 1,
                'due_date' => now()->addDays(3), // Через 3 дня
                'metadata' => [
                    'generation_id' => $generation->id,
                    'email_id' => $generation->email_id,
                    'analysis_type' => 'follow_up',
                    'parent_action' => $action
                ]
            ]);

            $taskIds[] = $task->id;
        }

        return $taskIds;
    }

    protected function createEscalationTask(array $analysis, Generation $generation): Task
    {
        $escalationLevel = $analysis['processing_requirements']['escalation_level'] ?? 'department_head';

        // Ищем руководителя для эскалации
        $manager = $this->findManagerForEscalation($analysis['department'] ?? 'general');

        return Task::create([
            'title' => "Эскалация: {$escalationLevel}",
            'content' => $this->buildEscalationContent($analysis),
            'status' => 'new',
            'priority' => 'urgent',
            'thread_id' => $generation->thread_id,
            'executor_id' => $manager?->id,
            'creator_id' => 1,
            'due_date' => now()->addDay(), // Завтра
            'metadata' => [
                'generation_id' => $generation->id,
                'email_id' => $generation->email_id,
                'analysis_type' => 'escalation',
                'escalation_level' => $escalationLevel
            ]
        ]);
    }

    protected function createRiskTask(array $analysis, Generation $generation): Task
    {
        $riskLevel = $analysis['processing_requirements']['legal_risks']['risk_level'];

        // Ищем специалиста по рискам
        $riskSpecialist = $this->findRiskSpecialist();

        return Task::create([
            'title' => "Анализ рисков: {$riskLevel}",
            'content' => $this->buildRiskContent($analysis),
            'status' => 'new',
            'priority' => $riskLevel === 'high' ? 'urgent' : 'high',
            'thread_id' => $generation->thread_id,
            'executor_id' => $riskSpecialist?->id,
            'creator_id' => 1,
            'due_date' => now()->addDays(2),
            'metadata' => [
                'generation_id' => $generation->id,
                'email_id' => $generation->email_id,
                'analysis_type' => 'risk_analysis',
                'risk_level' => $riskLevel
            ]
        ]);
    }

    protected function createApprovalTasks(array $analysis, Generation $generation): array
    {
        $taskIds = [];
        $approvalDepartments = $analysis['processing_requirements']['approval_departments'] ?? [];

        foreach ($approvalDepartments as $deptCode) {
            // Ищем представителя департамента для согласования
            $approver = $this->findDepartmentApprover($deptCode);

            $task = Task::create([
                'title' => "Согласование от " . Department::where('code', $deptCode)->value('name'),
                'content' => "Требуется согласование от департамента: {$deptCode}\n\n" . ($analysis['summary'] ?? ''),
                'status' => 'new',
                'priority' => 'high',
                'thread_id' => $this->getThreadId($generation),
                'executor_id' => $approver?->id,
                'creator_id' => 1,
                'due_date' => now()->addDays(1),
                'metadata' => [
                    'generation_id' => $generation->id,
                    'email_id' => $generation->email_id,
                    'analysis_type' => 'approval',
                    'department' => $deptCode
                ]
            ]);

            $taskIds[] = $task->id;
        }

        return $taskIds;
    }

    protected function archiveEmail(array $analysis, Generation $generation): void
    {
        // Создаем архивную задачу без исполнителя
        Task::create([
            'title' => $analysis['task_title'] ?? 'Архивное письмо',
            'content' => $this->buildArchivedContent($analysis),
            'status' => 'archived',
            'priority' => 'low',
            'thread_id' => $generation->thread_id,
            'executor_id' => null, // Доступно всем
            'creator_id' => 1,
            'due_date' => null,
            'metadata' => [
                'generation_id' => $generation->id,
                'email_id' => $generation->email_id,
                'analysis_type' => 'archived',
                'reason' => ($analysis['spam_check'] ?? 0) === 1 ? 'spam' : 'no_action_required'
            ]
        ]);

        Log::info("Email archived without task creation", [
            'generation_id' => $generation->id,
            'reason' => ($analysis['spam_check'] ?? 0) === 1 ? 'spam' : 'no_action_required'
        ]);
    }

    protected function findManagerForEscalation(string $departmentCode): ?User
    {
        return User::whereHas('department', function($query) use ($departmentCode) {
                    $query->where('code', $departmentCode);
                })
                ->where('is_active', true)
                ->where('role', 'manager')
                ->first();
    }

    protected function findRiskSpecialist(): ?User
    {
        return User::whereHas('department', function($query) {
                    $query->where('code', 'legal');
                })
                ->where('is_active', true)
                ->whereIn('role', ['manager', 'specialist'])
                ->first();
    }

    protected function findDepartmentApprover(string $departmentCode): ?User
    {
        return User::whereHas('department', function($query) use ($departmentCode) {
                    $query->where('code', $departmentCode);
                })
                ->where('is_active', true)
                ->whereIn('role', ['manager', 'specialist'])
                ->orderBy('last_task_assigned_at')
                ->first();
    }

    protected function parseDueDate(array $analysis): ?Carbon
    {
        // Получаем количество часов из анализа (новый формат)
        $deadlineHours = $analysis['deadline_hours'] ?? $analysis['processing_requirements']['sla_deadline_hours'] ?? null;

        if ($deadlineHours !== null && is_numeric($deadlineHours)) {
            $hours = (int) $deadlineHours;
            
            // Проверяем разумность значения (от 1 часа до 1 года)
            if ($hours > 0 && $hours <= 8760) { // 8760 часов = 365 дней
                return now()->addHours($hours);
            } else {
                Log::warning("Invalid deadline_hours value", [
                    'deadline_hours' => $hours,
                    'email_id' => $this->email->id
                ]);
            }
        }

        // Обратная совместимость: если ИИ вернул старое поле deadline (ISO datetime)
        $oldDeadline = $analysis['deadline'] ?? $analysis['processing_requirements']['sla_deadline'] ?? null;
        if ($oldDeadline && is_string($oldDeadline)) {
            try {
                return Carbon::parse($oldDeadline);
            } catch (\Exception $e) {
                Log::warning("Failed to parse legacy deadline format", [
                    'deadline' => $oldDeadline,
                    'error' => $e->getMessage(),
                    'email_id' => $this->email->id
                ]);
            }
        }

        return null;
    }

    protected function mapPriority(string $aiPriority): string
    {
        $priorityMap = [
            'urgent' => 'urgent',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low'
        ];

        return $priorityMap[$aiPriority] ?? 'medium';
    }

    protected function buildTaskContent(array $analysis): string
    {
        $content = [];

        if (isset($analysis['summary'])) {
            $content[] = "**Краткое содержание:**\n{$analysis['summary']}";
        }

        if (isset($analysis['core_request'])) {
            $content[] = "**Суть запроса:**\n{$analysis['core_request']}";
        }

        if (isset($analysis['key_points']) && is_array($analysis['key_points'])) {
            $content[] = "**Ключевые моменты:**\n" . implode("\n- ", $analysis['key_points']);
        }

        if (isset($analysis['suggested_response'])) {
            $content[] = "**Предлагаемый ответ:**\n{$analysis['suggested_response']}";
        }

        if (isset($analysis['action_recommendations']['immediate_actions'])) {
            $actions = $analysis['action_recommendations']['immediate_actions'];
            if (is_array($actions)) {
                $content[] = "**Немедленные действия:**\n" . implode("\n- ", $actions);
            }
        }

        return implode("\n\n", $content);
    }

    protected function buildEscalationContent(array $analysis): string
    {
        $content = "**ЭСКЛАЦИЯ**\n\n";

        if (isset($analysis['processing_requirements']['escalation_level'])) {
            $content .= "**Уровень эскалации:** {$analysis['processing_requirements']['escalation_level']}\n\n";
        }

        if (isset($analysis['summary'])) {
            $content .= "**Причина эскалации:**\n{$analysis['summary']}\n\n";
        }

        if (isset($analysis['processing_requirements']['legal_risks'])) {
            $risks = $analysis['processing_requirements']['legal_risks'];
            if (isset($risks['risk_factors']) && is_array($risks['risk_factors'])) {
                $content .= "**Связанные риски:**\n" . implode("\n- ", $risks['risk_factors']);
            }
        }

        return $content;
    }

    protected function buildRiskContent(array $analysis): string
    {
        $content = "**АНАЛИЗ РИСКОВ**\n\n";

        $risks = $analysis['processing_requirements']['legal_risks'] ?? [];

        if (isset($risks['risk_level'])) {
            $content .= "**Уровень риска:** {$risks['risk_level']}\n\n";
        }

        if (isset($risks['risk_factors']) && is_array($risks['risk_factors'])) {
            $content .= "**Факторы риска:**\n" . implode("\n- ", $risks['risk_factors']) . "\n\n";
        }

        if (isset($risks['recommended_actions']) && is_array($risks['recommended_actions'])) {
            $content .= "**Рекомендуемые действия:**\n" . implode("\n- ", $risks['recommended_actions']);
        }

        return $content;
    }

    protected function buildArchivedContent(array $analysis): string
    {
        $reason = ($analysis['spam_check'] ?? 0) === 1 ? 'спам' : 'не требует действий';

        $content = "**АРХИВНОЕ ПИСЬМО**\n";
        $content .= "**Причина:** {$reason}\n\n";

        if (isset($analysis['summary'])) {
            $content .= "**Содержание:**\n{$analysis['summary']}";
        }

        return $content;
    }

}
