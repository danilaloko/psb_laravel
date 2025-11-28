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

    public function __construct(Email $email)
    {
        $this->email = $email;
        $this->onQueue('ai-processing'); // Специальная очередь для ИИ задач
    }

    public function handle(YandexAIService $aiService): void
    {
        $startTime = microtime(true);

        try {
            // Получаем модель из конфига
            $modelConfig = config('ai-models.yandex.' . config('ai-models.default_model'));

            // Формируем промпт
            $prompt = $this->buildPrompt($this->email->content);

            // Отправляем запрос в Yandex AI Studio
            $response = $aiService->generateCompletion($prompt, $modelConfig);

            // Вычисляем время обработки
            $processingTime = round(microtime(true) - $startTime, 3);

            // Парсим ответ от Yandex AI
            $parsedResponse = $this->parseYandexResponse($response);

            // Сохраняем результат
            $this->saveGeneration($parsedResponse, $processingTime, $modelConfig, $response);

            Log::info("Email {$this->email->id} processed successfully", [
                'processing_time' => $processingTime,
                'model' => $modelConfig['name']
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
            'error' => $exception->getMessage()
        ]);

        // Можно отправить уведомление администратору
        // или пометить email для ручной обработки
    }

    protected function buildPrompt(string $emailContent): string
    {
        $template = config('ai-models.prompts.email_analysis.user_template');

        return str_replace(
            ['{email_content}', '{response_format}'],
            [$emailContent, $this->getResponseFormat()],
            $template
        );
    }

    protected function getResponseFormat(): string
    {
        return json_encode([
            'summary' => 'string',
            'priority' => 'high|medium|low',
            'category' => 'complaint|request|information|support',
            'sentiment' => 'positive|neutral|negative',
            'action_required' => 'boolean',
            'suggested_response' => 'string',
            'key_points' => 'array',
            'deadline' => 'ISO datetime or null'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function saveGeneration(array $parsedResponse, float $processingTime, array $modelConfig, array $apiResponse): void
    {
        Generation::create([
            'email_id' => $this->email->id,
            'prompt' => $this->buildPrompt($this->email->content),
            'response' => $parsedResponse, // Уже распарсенный JSON
            'processing_time' => $processingTime,
            'status' => 'success',
            'metadata' => [
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
            ]
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
                'summary' => 'Не удалось распарсить ответ ИИ',
                'priority' => 'medium',
                'category' => 'error',
                'sentiment' => 'neutral',
                'action_required' => true,
                'suggested_response' => 'Требуется ручная обработка',
                'key_points' => ['Ошибка парсинга ответа ИИ'],
                'deadline' => null
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
