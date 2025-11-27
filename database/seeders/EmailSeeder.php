<?php

namespace Database\Seeders;

use App\Models\Email;
use App\Models\Thread;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $threadTitles = [
            'Проблема с авторизацией в системе',
            'Запрос на техническую поддержку',
            'Жалоба на работу сервиса',
            'Предложение по улучшению интерфейса',
            'Вопрос по тарифам и оплате',
            'Проблема с загрузкой файлов',
            'Запрос на восстановление доступа',
            'Вопрос по документации',
            'Обратная связь от пользователей',
            'Технический инцидент',
        ];

        $emailSubjects = [
            'Срочная проблема с входом',
            'Не могу получить доступ к аккаунту',
            'Система выдает ошибку при авторизации',
            'Забыл пароль, как восстановить?',
            'Не приходит письмо с подтверждением',
            'Проблема с двухфакторной аутентификацией',
            'Аккаунт заблокирован без причины',
            'Не работает восстановление пароля',
            'Ошибка при регистрации нового пользователя',
            'Проблема с входом через социальные сети',
        ];

        // Создаем потоки
        foreach ($threadTitles as $index => $title) {
            $thread = Thread::create([
                'title' => $title,
                'status' => fake()->randomElement(['active', 'completed', 'archived']),
            ]);

            // Создаем 1-5 писем для каждого потока
            $emailCount = rand(1, 5);
            for ($i = 0; $i < $emailCount; $i++) {
                Email::create([
                    'subject' => fake()->randomElement($emailSubjects),
                    'content' => fake()->paragraphs(rand(2, 5), true),
                    'thread_id' => $thread->id,
                    'from_address' => fake()->safeEmail(),
                    'from_name' => fake()->name(),
                    'received_at' => fake()->dateTimeBetween('-30 days', 'now'),
                ]);
            }
        }
    }
}
