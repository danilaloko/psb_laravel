<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Thread;
use App\Jobs\ProcessEmailWithAI;
use Webklex\IMAP\Facades\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmailFetcherService
{
    public function __construct()
    {
        //
    }

    /**
     * Тест подключения к IMAP серверу
     */
    public function testConnection(): bool
    {
        try {
            $client = Client::account('default');
            $client->connect();
            $client->disconnect();
            return true;
        } catch (\Exception $e) {
            Log::error('IMAP test connection failed: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Получение писем за последние N минут
     */
    public function fetchRecentEmails(int $minutes = 60): array
    {
        try {
            $client = Client::account('default');
            $client->connect();

            $since = Carbon::now()->subMinutes($minutes);
            $limit = 100; // Устанавливаем больший лимит для поиска всех писем

            Log::info('Searching for emails', [
                'since' => $since->format('Y-m-d H:i:s'),
                'minutes' => $minutes,
                'limit' => $limit
            ]);

            // Получаем папку INBOX
            $inbox = $client->getFolder('INBOX');

            // Ищем письма с момента $since
            $messages = $inbox->query()->since($since)->limit($limit)->get();

            Log::info('Found messages', ['count' => $messages->count()]);

            $processed = 0;
            $skipped = 0;

            foreach ($messages as $message) {
                if ($this->processMessage($message)) {
                    $processed++;
                } else {
                    $skipped++;
                }
            }

            $client->disconnect();

            Log::info("Email fetch completed. Processed: {$processed}, Skipped: {$skipped}");

            return [
                'processed' => $processed,
                'skipped' => $skipped,
                'total' => $messages->count()
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching emails: ' . $e->getMessage());
            return [
                'processed' => 0,
                'skipped' => 0,
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Обработка одного письма
     */
    private function processMessage($message): bool
    {
        try {
            $messageId = $message->getMessageId();

            // Проверяем, не обрабатывали ли уже это письмо
            if (Email::where('message_id', $messageId)->exists()) {
                Log::debug("Email already processed: {$messageId}");
                return false;
            }

            // Получаем данные письма
            $subjectRaw = $message->getSubject() ?: 'Без темы';
            $subject = $this->decodeMimeHeader($subjectRaw);
            $content = $this->getMessageContent($message);
            $from = $message->getFrom()[0] ?? null;
            $to = $message->getTo()[0] ?? null;
            $receivedAt = $message->getDate();

            if (!$from) {
                Log::warning("Email without sender: {$messageId}");
                return false;
            }

            // Декодируем имя отправителя
            $fromName = $from->personal ? $this->decodeMimeHeader($from->personal) : null;

            // Создаем или находим thread
            $thread = Thread::firstOrCreate([
                'title' => $subject
            ]);

            // Создаем email запись
            $email = Email::create([
                'message_id' => $messageId,
                'subject' => $subject,
                'content' => $content,
                'thread_id' => $thread->id,
                'from_address' => $from->mail,
                'from_name' => $fromName,
                'received_at' => $receivedAt,
            ]);

            // Запускаем обработку ИИ
            ProcessEmailWithAI::dispatch($email);

            Log::info("New email processed: {$messageId} - {$subject}");

            return true;

        } catch (\Exception $e) {
            Log::error('Error processing message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Декодирование MIME заголовков (Base64, Quoted-Printable)
     */
    private function decodeMimeHeader(string $header): string
    {
        // Если заголовок уже декодирован или пустой, возвращаем как есть
        if (empty($header) || strpos($header, '=?') === false) {
            return $header;
        }

        try {
            // Используем mb_decode_mimeheader для декодирования
            $decoded = mb_decode_mimeheader($header);
            
            // Если декодирование не сработало, пробуем iconv
            if ($decoded === $header && function_exists('iconv_mime_decode')) {
                $decoded = iconv_mime_decode($header, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            }
            
            return $decoded ?: $header;
        } catch (\Exception $e) {
            Log::warning('Error decoding MIME header: ' . $e->getMessage(), [
                'header' => substr($header, 0, 100)
            ]);
            return $header;
        }
    }

    /**
     * Получение содержимого письма
     */
    private function getMessageContent($message): string
    {
        try {
            // Сначала пробуем получить HTML версию
            if ($message->hasHTMLBody()) {
                return $message->getHTMLBody();
            }

            // Если HTML нет, получаем текстовую версию
            if ($message->hasTextBody()) {
                return $message->getTextBody();
            }

            // Если ничего нет, возвращаем пустую строку
            return '';

        } catch (\Exception $e) {
            Log::warning('Error getting message content: ' . $e->getMessage());
            return '';
        }
    }

}
