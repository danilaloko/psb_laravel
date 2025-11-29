<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailWithAI;
use App\Models\Email;
use App\Models\Generation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class TestAIProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:test-processing {--email= : ID существующего email для тестирования}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование обработки email с помощью Yandex AI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Запуск тестирования AI обработки email');
        $this->newLine();

        // Проверяем наличие IAM токена
        if (!config('services.yandex.iam_token')) {
            $this->error('❌ YANDEX_IAM_TOKEN не настроен в .env файле');
            $this->line('Добавьте YANDEX_IAM_TOKEN=ваш_токен в .env файл');
            return 1;
        }

        if (!config('services.yandex.folder_id')) {
            $this->error('❌ YANDEX_FOLDER_ID не настроен в .env файле');
            $this->line('Добавьте YANDEX_FOLDER_ID=ваш_folder_id в .env файл');
            return 1;
        }

        $emailId = $this->option('email');

        if ($emailId) {
            // Используем существующий email
            $email = Email::find($emailId);
            if (!$email) {
                $this->error("❌ Email с ID {$emailId} не найден");
                return 1;
            }
            $this->info("📧 Используем существующий email ID: {$email->id}");
        } else {
            // Создаем новый тестовый email
            $email = $this->createTestEmail();
            $this->info("📧 Создан новый тестовый email ID: {$email->id}");
        }

        // Показываем информацию об email
        $this->displayEmailInfo($email);

        // Запоминаем время запуска для отслеживания новой генерации
        $startTime = Carbon::now();

        // Проверяем, есть ли уже генерации для этого email
        $existingGenerationsCount = Generation::where('email_id', $email->id)->count();
        if ($existingGenerationsCount > 0) {
            $this->warn("⚠️  Для этого email уже есть {$existingGenerationsCount} генераций. Создадим новую.");
        }

        // Запускаем обработку
        $this->info('🤖 Запускаем AI обработку...');
        ProcessEmailWithAI::dispatch($email);

        // Ждем результата - новой генерации, созданной после запуска
        $this->info('⏳ Ожидаем результат обработки...');
        $bar = $this->output->createProgressBar(30);
        $bar->start();

        $generation = null;
        for ($i = 0; $i < 30; $i++) {
            $generation = Generation::where('email_id', $email->id)
                ->where('created_at', '>', $startTime)
                ->latest()
                ->first();

            if ($generation) {
                $bar->finish();
                $this->newLine(2);
                break;
            }

            sleep(1);
            $bar->advance();
        }

        if (!$generation) {
            $bar->finish();
            $this->newLine();
            $this->error('❌ Превышено время ожидания результата');
            $this->line('Проверьте логи и статус очереди:');
            $this->line('php artisan queue:failed');
            return 1;
        }

        // Показываем результат
        $this->displayAIResult($generation);

        // Статистика
        $this->displayStats($generation);

        $this->newLine();
        $this->info('✅ Тестирование завершено успешно!');

        return 0;
    }

    protected function createTestEmail(): Email
    {
        return Email::create([
            'subject' => 'Проблема с доступом к личному кабинету',
            'content' => 'Уважаемая служба поддержки!

Сегодня утром я пытался войти в свой личный кабинет на вашем сайте, но система выдала ошибку: "Неверный пароль". При этом я уверен, что ввожу правильный пароль, который использовал еще вчера.

Я пробовал:
1. Восстановить пароль через "Забыли пароль?"
2. Очистить кэш браузера
3. Попробовать войти с другого устройства

Но ничего не помогает. Мой аккаунт зарегистрирован более года назад, и у меня там хранятся важные документы.

Прошу срочно помочь восстановить доступ к аккаунту. Мои контактные данные:
- Email: ivan.petrov@example.com
- Телефон: +7 (999) 123-45-67

Буду благодарен за оперативное решение проблемы!

С уважением,
Иван Петров',
            'thread_id' => 1,
            'from_address' => 'ivan.petrov@example.com',
            'from_name' => 'Иван Петров',
            'received_at' => now(),
        ]);
    }

    protected function displayEmailInfo(Email $email): void
    {
        $this->line('📄 <comment>Информация об email:</comment>');
        $this->line("   ID: <info>{$email->id}</info>");
        $this->line("   Тема: <info>{$email->subject}</info>");
        $this->line("   От: <info>{$email->from_name} <{$email->from_address}></info>");
        $this->line("   Дата: <info>{$email->received_at->format('d.m.Y H:i')}</info>");
        $this->line("   Длина текста: <info>" . strlen($email->content) . " символов</info>");
        $this->newLine();
    }

    protected function displayAIResult(Generation $generation): void
    {
        $this->line('🎯 <comment>Результаты AI анализа:</comment>');
        $this->line('   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $response = $generation->response;

        if (is_array($response)) {
            // Новые поля для задач
            $this->line("   📋 <info>Название задачи:</info> " . ($response['task_title'] ?? 'N/A'));
            $this->line("   🏢 <info>Департамент:</info> " . ($response['department'] ?? 'N/A'));
            $this->line("   🔥 <info>Приоритет задачи:</info> " . ($response['task_priority'] ?? 'N/A'));

            $this->line("   📝 <info>Краткое содержание:</info> " . ($response['summary'] ?? 'N/A'));
            $this->line("   📂 <info>Категория:</info> " . ($response['category'] ?? 'N/A'));
            $this->line("   😊 <info>Тональность:</info> " . ($response['sentiment'] ?? 'N/A'));
            $this->line("   ⚡ <info>Требуется действие:</info> " . (($response['action_required'] ?? false) ? 'Да' : 'Нет'));

            if (!empty($response['suggested_response'])) {
                $this->newLine();
                $this->line("   💬 <info>Предложенный ответ:</info>");
                $this->line("      <comment>{$response['suggested_response']}</comment>");
            }

            if (!empty($response['key_points'])) {
                $this->newLine();
                $this->line("   🔑 <info>Ключевые моменты:</info>");
                foreach ($response['key_points'] as $point) {
                    $this->line("      • <comment>{$point}</comment>");
                }
            }

            if (!empty($response['deadline'])) {
                $this->line("   ⏰ <info>Срок выполнения:</info> {$response['deadline']}");
            }
        } else {
            $this->line("   <error>❌ Ошибка парсинга ответа AI</error>");
        }

        $this->newLine();
    }

    protected function displayStats(Generation $generation): void
    {
        $this->line('📊 <comment>Статистика обработки:</comment>');
        $this->line("   ⏱️  Время обработки: <info>{$generation->processing_time} сек</info>");
        $this->line("   🤖 Модель: <info>" . ($generation->getModelName() ?? 'N/A') . "</info>");
        $this->line("   📊 Токены: <info>" . ($generation->getTotalTokens() ?? 'N/A') . "</info>");
        $this->line("   💰 Стоимость: <info>" . ($generation->getCost() ?? 0) . " RUB</info>");
        $this->newLine();
    }
}
