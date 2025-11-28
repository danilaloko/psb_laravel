<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailWithAI;
use App\Models\Task;
use Illuminate\Console\Command;

class TestTaskAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:task-analysis {task_id=57 : ID задачи для тестирования}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Тестирование анализа задачи с polling';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $taskId = $this->argument('task_id');
        $task = Task::find($taskId);

        if (!$task) {
            $this->error("Задача с ID {$taskId} не найдена");
            return 1;
        }

        $this->info("🧪 Тестирование анализа задачи ID: {$taskId}");
        $this->info("📝 Название: {$task->title}");

        // Найти последний email в thread задачи
        $latestEmail = $task->thread->emails()->latest('received_at')->first();

        if (!$latestEmail) {
            $this->error("❌ В задаче нет emails");
            return 1;
        }

        $this->info("📧 Найден email ID: {$latestEmail->id}");
        $this->info("📧 Тема: {$latestEmail->subject}");

        // Запустить анализ
        $this->info("🚀 Запускаем анализ...");
        ProcessEmailWithAI::dispatch($latestEmail);

        $this->info("✅ Анализ запущен");
        $this->info("💡 Для тестирования UI откройте страницу задачи в браузере");

        return 0;
    }
}
