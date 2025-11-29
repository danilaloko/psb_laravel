<?php

namespace App\Console\Commands;

use App\Jobs\CreateTasksFromAnalysis;
use App\Models\Generation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAnalysisToTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analysis:process-to-tasks {--limit=10 : Количество анализов для обработки} {--force : Обработать все анализы, включая уже обработанные}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обработка результатов анализа ИИ и создание задач';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $this->info("Начинаем обработку результатов анализа ИИ...");

        try {
            // Строим запрос для поиска анализов
            $query = Generation::where('type', 'analysis')
                              ->where('status', 'success')
                              ->where('is_spam', false); // Исключаем спам

            // Если не force, то только те, для которых еще не созданы задачи
            if (!$force) {
                $query->whereRaw("JSON_EXTRACT(metadata, '$.tasks_created') IS NULL OR JSON_EXTRACT(metadata, '$.tasks_created') != true");
            }

            $analyses = $query->limit($limit)->get();

            if ($analyses->isEmpty()) {
                $this->info("Нет анализов для обработки.");
                return Command::SUCCESS;
            }

            $this->info("Найдено анализов: {$analyses->count()}");

            $processed = 0;

            $progressBar = $this->output->createProgressBar($analyses->count());
            $progressBar->start();

            foreach ($analyses as $analysis) {
                try {
                    // Запускаем job для создания задач
                    CreateTasksFromAnalysis::dispatch($analysis);

                    $processed++;
                    $progressBar->advance();

                } catch (\Exception $e) {
                    $this->error("Ошибка при обработке анализа ID {$analysis->id}: " . $e->getMessage());
                    Log::error("Failed to process analysis to tasks", [
                        'analysis_id' => $analysis->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("Обработка завершена:");
            $this->line("• Отправлено в очередь: {$processed}");

            if ($processed > 0) {
                $this->info("Задачи будут созданы в фоновом режиме через очередь 'task-creation'.");
            }

            Log::info("Analysis to tasks processing completed", [
                'processed' => $processed,
                'total_found' => $analyses->count()
            ]);

        } catch (\Exception $e) {
            $this->error("Произошла ошибка: " . $e->getMessage());
            Log::error("Analysis to tasks command failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
