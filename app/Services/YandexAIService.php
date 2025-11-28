<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexAIService
{
    protected string $baseUrl = 'https://llm.api.cloud.yandex.net';
    protected ?string $iamToken;
    protected ?string $folderId;

    public function __construct()
    {
        $this->iamToken = config('services.yandex.iam_token');
        $this->folderId = config('services.yandex.folder_id');
    }

    public function generateCompletion(string $prompt, array $modelConfig): array
    {
        if (!$this->iamToken || !$this->folderId) {
            throw new \InvalidArgumentException('Yandex Cloud credentials not configured. Please set YANDEX_IAM_TOKEN and YANDEX_FOLDER_ID in .env');
        }

        $requestBody = [
            'modelUri' => "gpt://{$this->folderId}/{$modelConfig['model']}/{$modelConfig['version']}",
            'completionOptions' => [
                'stream' => false,
                'temperature' => $modelConfig['temperature'],
                'maxTokens' => $modelConfig['max_tokens']
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'text' => config('ai-models.prompts.email_analysis.system')
                ],
                [
                    'role' => 'user',
                    'text' => $prompt
                ]
            ]
        ];

        Log::info('Sending request to Yandex AI', [
            'model_uri' => $requestBody['modelUri'],
            'prompt_length' => strlen($prompt)
        ]);

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => "Bearer {$this->iamToken}",
                'Content-Type' => 'application/json'
            ])
            ->post("{$this->baseUrl}/foundationModels/v1/completion", $requestBody);

        Log::info('Yandex AI response received', [
            'status' => $response->status(),
            'response_length' => strlen($response->body())
        ]);

        if (!$response->successful()) {
            Log::error('Yandex AI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'request_body' => $requestBody
            ]);

            throw new \Exception("Yandex AI API error: {$response->status()} - {$response->body()}");
        }

        $result = $response->json();
        Log::info('Yandex AI response parsed', [
            'has_result' => isset($result['result']),
            'has_alternatives' => isset($result['result']['alternatives']),
            'alternatives_count' => isset($result['result']['alternatives']) ? count($result['result']['alternatives']) : 0,
            'full_response_keys' => array_keys($result)
        ]);

        // Yandex AI возвращает данные в поле "result"
        return $result['result'] ?? $result;
    }
}
