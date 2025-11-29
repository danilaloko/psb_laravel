<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XpasteService
{
    protected string $baseUrl = 'https://xpaste.pro';

    public function __construct()
    {
        // Можно переопределить через конфиг, если понадобится корпоративный инстанс
        $this->baseUrl = config('services.xpaste.base_url', 'https://xpaste.pro');
    }

    /**
     * Создать заметку в xpaste
     *
     * @param string $body Текст заметки (обязательно, до 512 KB)
     * @param string $language Язык: 'text' или 'markdown' (по умолчанию 'text')
     * @param bool $autoDestroy Удалить после первого просмотра (по умолчанию false)
     * @param int $ttlDays Сколько дней хранить (по умолчанию 365)
     * @return string URL созданной заметки
     * @throws \Exception При ошибке API
     */
    public function createPaste(
        string $body,
        string $language = 'text',
        bool $autoDestroy = false,
        int $ttlDays = 365
    ): string {
        // Валидация размера (512 KB = 524288 байт)
        if (strlen($body) > 524288) {
            throw new \InvalidArgumentException('Размер текста не должен превышать 512 KB');
        }

        // Валидация языка
        if (!in_array($language, ['text', 'markdown'])) {
            throw new \InvalidArgumentException("Язык должен быть 'text' или 'markdown'");
        }

        $url = "{$this->baseUrl}/paste";
        
        // Формируем данные для form-data запроса
        $data = [
            'body' => $body,
            'language' => $language,
        ];
        
        // Добавляем auto_destroy только если true (по умолчанию false)
        if ($autoDestroy) {
            $data['auto_destroy'] = 'true';
        }
        
        // Добавляем ttl_days только если отличается от дефолтного значения
        if ($ttlDays !== 365) {
            $data['ttl_days'] = $ttlDays;
        }

        Log::info('Creating xpaste note', [
            'url' => $url,
            'body_length' => strlen($body),
            'language' => $language,
            'auto_destroy' => $autoDestroy,
            'ttl_days' => $ttlDays,
        ]);

        try {
            // Отправляем form-data запрос (без Accept: application/json для получения редиректа)
            // withoutRedirecting() нужен, чтобы получить Location header вместо автоматического следования редиректу
            $response = Http::timeout(30)
                ->withoutRedirecting() // Не следовать редиректу автоматически, чтобы получить Location header
                ->asForm() // Отправляем как form-data
                ->post($url, $data);

            Log::info('Xpaste API response received', [
                'status' => $response->status(),
                'location' => $response->header('Location'),
            ]);

            // API возвращает редирект 302 с Location header
            $location = $response->header('Location');
            if ($location) {
                // Если относительный URL, делаем абсолютным
                if (str_starts_with($location, '/')) {
                    return $this->baseUrl . $location;
                }
                // Если уже абсолютный URL, возвращаем как есть
                return $location;
            }

            // Если редиректа нет, но статус успешный, проверяем JSON ответ (на случай если API изменится)
            if ($response->successful()) {
                $contentType = $response->header('Content-Type');
                if ($contentType && str_contains($contentType, 'application/json')) {
                    $json = $response->json();
                    // API возвращает permalink, из которого формируем полный URL
                    if (isset($json['permalink'])) {
                        return "{$this->baseUrl}/{$json['permalink']}";
                    }
                    // Если в JSON есть прямая ссылка
                    if (isset($json['url']) || isset($json['link'])) {
                        return $json['url'] ?? $json['link'];
                    }
                }
            }

            // Пытаемся извлечь ссылку из HTML ответа
            $body = $response->body();
            if (preg_match('/https?:\/\/[^\s<>"]+/', $body, $matches)) {
                // Ищем ссылку на xpaste
                foreach ($matches as $match) {
                    if (str_contains($match, $this->baseUrl)) {
                        return $match;
                    }
                }
                // Если не нашли ссылку на xpaste, возвращаем первую найденную
                return $matches[0];
            }

            // Если ничего не нашли, но ответ успешный, пробуем извлечь из редиректа
            if ($response->successful()) {
                // Возможно, ответ содержит редирект в виде HTML meta refresh или JavaScript
                if (preg_match('/window\.location\s*=\s*["\']([^"\']+)["\']/', $body, $jsMatches)) {
                    return $jsMatches[1];
                }
                if (preg_match('/content=["\']0;\s*url=([^"\']+)["\']/', $body, $metaMatches)) {
                    return $metaMatches[1];
                }
            }

            // Если ничего не получилось, но ответ успешный - возвращаем базовый URL
            // (возможно, нужно будет доработать парсинг)
            if ($response->successful()) {
                Log::warning('Could not extract paste URL from response', [
                    'status' => $response->status(),
                    'body_preview' => substr($body, 0, 500),
                ]);
                throw new \Exception('Не удалось извлечь ссылку на созданную заметку из ответа API');
            }

            // Обработка ошибок
            Log::error('Xpaste API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception("Ошибка API xpaste: {$response->status()} - {$response->body()}");

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Xpaste API connection error', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Ошибка подключения к API xpaste: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Xpaste API unexpected error', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

