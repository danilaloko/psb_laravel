<?php

namespace Tests\Unit;

use App\Services\YandexAIService;
use Tests\TestCase;

class YandexAIServiceTest extends TestCase
{
    /**
     * Тест что сервис имеет метод searchVectorStore
     */
    public function test_service_has_search_method(): void
    {
        $service = app(YandexAIService::class);

        $this->assertTrue(method_exists($service, 'searchVectorStore'));
    }

    /**
     * Тест что сервис делает реальный запрос к API (если есть credentials)
     */
    public function test_service_makes_api_request(): void
    {
        $service = app(YandexAIService::class);

        // Если credentials не настроены, ожидаем InvalidArgumentException
        if (!config('services.yandex.iam_token') || !config('services.yandex.folder_id')) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Yandex Cloud credentials not configured');
            $service->searchVectorStore('test-index', 'test query');
            return;
        }

        // Если credentials настроены, ожидаем Exception с ошибкой API (индекс не найден)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Vector Store search failed');
        $service->searchVectorStore('test-index', 'test query');
    }
}
