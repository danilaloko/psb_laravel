<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $threads = Thread::all();

        if ($users->isEmpty() || $threads->isEmpty()) {
            return;
        }

        $titles = [
            'Разрешить проблему с авторизацией',
            'Обработать запрос технической поддержки',
            'Рассмотреть жалобу на сервис',
            'Реализовать предложение по улучшению',
            'Обработать запрос на возврат средств',
            'Разъяснить вопросы по тарифам',
            'Исправить проблему с авторизацией',
            'Внести изменения в данные пользователя',
            'Подготовить документацию',
            'Обработать обратную связь',
        ];

        $statuses = ['new', 'in_progress', 'completed', 'archived', 'cancelled'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        foreach ($users as $user) {
            // Создаем 3-10 задач для каждого пользователя
            $taskCount = rand(3, 10);

            for ($i = 0; $i < $taskCount; $i++) {
                $thread = $threads->random();
                $creator = $users->random();

                Task::create([
                    'title' => fake()->randomElement($titles),
                    'content' => fake()->paragraphs(rand(2, 5), true),
                    'status' => fake()->randomElement($statuses),
                    'priority' => fake()->randomElement($priorities),
                    'thread_id' => $thread->id,
                    'executor_id' => $user->id,
                    'creator_id' => $creator->id,
                    'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+30 days'),
                ]);
            }
        }
    }
}
