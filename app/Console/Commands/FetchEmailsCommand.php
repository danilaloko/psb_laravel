<?php

namespace App\Console\Commands;

use App\Services\EmailFetcherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:fetch {--minutes=60 : Период получения писем в минутах} {--test-connection : Только тест подключения}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Получение новых писем с IMAP сервера';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = (int) $this->option('minutes');
        $testConnection = $this->option('test-connection');

        $this->info("Начинаем получение писем за последние {$minutes} минут...");

        try {
            $fetcher = new \App\Services\EmailFetcherService();

            if ($testConnection) {
                $this->info("Тестируем подключение к IMAP...");
                $connected = $fetcher->testConnection();

                if ($connected) {
                    $this->info("✅ Подключение успешно!");
                } else {
                    $this->error("❌ Ошибка подключения");
                }
                return;
            }

            $result = $fetcher->fetchRecentEmails($minutes);

            if (is_array($result)) {
                $this->info("Обработка завершена:");
                $this->line("• Обработано писем: {$result['processed']}");
                $this->line("• Пропущено дубликатов: {$result['skipped']}");
                $this->line("• Всего найдено: {$result['total']}");

                if (isset($result['error'])) {
                    $this->error("Ошибка: {$result['error']}");
                }

                Log::info("Email fetch command completed", $result);
            } else {
                $this->error("Ошибка при получении писем");
                Log::error("Email fetch command failed");
            }

        } catch (\Exception $e) {
            $this->error("Произошла ошибка: " . $e->getMessage());
            Log::error("Email fetch command exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
