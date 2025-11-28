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

    /**
     * Получить список всех доступных поисковых индексов из Yandex AI Studio
     *
     * Использует REST Assistant API: https://rest-assistant.api.cloud.yandex.net/assistants/v1/searchIndex
     * Метод: SearchIndex.List
     */
    public function getSearchIndexes(): array
    {
        if (!$this->iamToken || !$this->folderId) {
            throw new \InvalidArgumentException('Yandex Cloud credentials not configured');
        }

        // Возможные endpoints для поиска индексов (нужно найти правильный):
        // - https://search.indexing.api.cloud.yandex.net/
        // - https://search.api.cloud.yandex.net/
        // - https://ai.api.cloud.yandex.net/
        // - или другой

        $searchApiUrl = 'https://search.indexing.api.cloud.yandex.net';

        Log::info('Fetching search indexes from Yandex AI Studio', [
            'folder_id' => $this->folderId,
            'endpoint' => $searchApiUrl
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->iamToken}",
                    'Content-Type' => 'application/json'
                ])
                ->get("https://rest-assistant.api.cloud.yandex.net/assistants/v1/searchIndex", [
                    'folderId' => $this->folderId,
                    'pageSize' => 100
                ]);

            Log::info('Yandex AI Studio Search API response received', [
                'status' => $response->status(),
                'response_length' => strlen($response->body())
            ]);

            if (!$response->successful()) {
                Log::error('Yandex AI Studio Search API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'folder_id' => $this->folderId
                ]);

                // Возвращаем пустой массив при ошибке, чтобы не ломать интерфейс
                return [];
            }

            $result = $response->json();

            // Структура ответа: { "indices": [...] }
            $indices = $result['indices'] ?? [];

            Log::info('Successfully retrieved search indices', [
                'count' => count($indices),
                'folder_id' => $this->folderId
            ]);

            // Преобразуем структуру для удобства использования
            $formattedIndices = array_map(function ($index) {
                return [
                    'id' => $index['id'],
                    'name' => $index['name'] ?? 'Без названия',
                    'description' => $index['description'] ?? null,
                    'status' => 'READY', // В API ответе статус может быть другим, но для отображения используем READY
                    'created_at' => $index['createdAt'] ?? null,
                    'updated_at' => $index['updatedAt'] ?? null,
                    'folder_id' => $index['folderId'] ?? null,
                    'labels' => $index['labels'] ?? [],
                    'text_search_index' => $index['textSearchIndex'] ?? null,
                    'vector_search_index' => $index['vectorSearchIndex'] ?? null,
                    'hybrid_search_index' => $index['hybridSearchIndex'] ?? null,
                ];
            }, $indices);

            return $formattedIndices;

        } catch (\Exception $e) {
            Log::error('Exception while fetching search indices', [
                'error' => $e->getMessage(),
                'folder_id' => $this->folderId
            ]);

            // Возвращаем пустой массив при ошибке
            return [];
        }
    }

    /**
     * Получить информацию об конкретном индексе
     */
    public function getSearchIndex(string $indexId): ?array
    {
        if (!$this->iamToken) {
            throw new \InvalidArgumentException('Yandex Cloud credentials not configured');
        }

        $searchApiUrl = 'https://search.indexing.api.cloud.yandex.net';

        Log::info('Fetching search index info', ['index_id' => $indexId]);

        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => "Bearer {$this->iamToken}",
                'Content-Type' => 'application/json'
            ])
            ->get("{$searchApiUrl}/search/v1/indexes/{$indexId}");

        if ($response->status() === 404) {
            Log::info('Index not found', ['index_id' => $indexId]);
            return null;
        }

        if (!$response->successful()) {
            Log::error('Yandex Search API error for index', [
                'index_id' => $indexId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception("Failed to get index info: {$response->status()}");
        }

        return $response->json();
    }
}
