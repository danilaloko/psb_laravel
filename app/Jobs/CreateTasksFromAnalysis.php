<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\Generation;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateTasksFromAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected Generation $generation;

    public function __construct(Generation $generation)
    {
        $this->generation = $generation;
        $this->onQueue('task-creation');
    }

    public function handle(): void
    {
        try {
            // Перезагружаем generation для корректной работы
            $this->generation = Generation::find($this->generation->id);

            if (!$this->generation) {
                Log::warning("Generation not found for task creation", [
                    'generation_id' => $this->generation->id ?? null
                ]);
                return;
            }

            $analysis = $this->generation->response;

            // Проверяем условия создания задач
            if ($this->shouldSkipTaskCreation($analysis)) {
                $this->archiveEmail($analysis);
                return;
            }

            // Создаем задачи
            $createdTasks = $this->createTasks($analysis);

            // Обновляем статус generation
            $this->generation->update([
                'metadata' => array_merge($this->generation->metadata ?? [], [
                    'tasks_created' => true,
                    'created_tasks_count' => count($createdTasks),
                    'created_tasks_ids' => $createdTasks
                ])
            ]);

            Log::info("Tasks created from analysis", [
                'generation_id' => $this->generation->id,
                'email_id' => $this->generation->email_id,
                'tasks_count' => count($createdTasks),
                'task_ids' => $createdTasks
            ]);

        } catch (Throwable $e) {
            Log::error("Failed to create tasks from analysis", [
                'generation_id' => $this->generation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    protected function shouldSkipTaskCreation(array $analysis): bool
    {
        // Проверяем на спам
        if (($analysis['spam_check'] ?? 0) === 1) {
            return true;
        }

        // Проверяем статус анализа
        if ($this->generation->status !== 'success') {
            return true;
        }

        // Проверяем необходимость действий
        if (($analysis['action_required'] ?? false) === false) {
            return true;
        }

        return false;
    }

    protected function createTasks(array $analysis): array
    {
        $createdTaskIds = [];

        // Определяем executor_id
        $executorId = $this->findBestExecutor($analysis['department'] ?? 'general');

        // Создаем основную задачу
        $mainTask = $this->createMainTask($analysis, $executorId);
        $createdTaskIds[] = $mainTask->id;

        // Создаем дополнительные задачи
        $followUpTasks = $this->createFollowUpTasks($analysis, $executorId);
        $createdTaskIds = array_merge($createdTaskIds, $followUpTasks);

        // Создаем задачи эскалации если нужно
        if ($analysis['processing_requirements']['escalation_required'] ?? false) {
            $escalationTask = $this->createEscalationTask($analysis);
            $createdTaskIds[] = $escalationTask->id;
        }

        // Создаем задачи по рискам если нужно
        if (($analysis['processing_requirements']['legal_risks']['risk_level'] ?? 'none') !== 'none') {
            $riskTask = $this->createRiskTask($analysis);
            $createdTaskIds[] = $riskTask->id;
        }

        // Создаем задачи по одобрениям
        $approvalTasks = $this->createApprovalTasks($analysis);
        $createdTaskIds = array_merge($createdTaskIds, $approvalTasks);

        return $createdTaskIds;
    }

    protected function findBestExecutor(string $departmentCode): ?int
    {
        // Ищем самого незагруженного пользователя в департаменте
        $user = User::select('users.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('tasks', function($join) {
                $join->on('users.id', '=', 'tasks.executor_id')
                     ->whereIn('tasks.status', ['new', 'in_progress']);
            })
            ->where('departments.code', $departmentCode)
            ->where('users.is_active', true)
            ->groupBy('users.id')
            ->orderByRaw('COUNT(tasks.id) ASC, users.last_task_assigned_at ASC')
            ->first();

        if ($user) {
            // Обновляем время последнего назначения
            User::where('id', $user->id)->update([
                'last_task_assigned_at' => now()
            ]);

            return $user->id;
        }

        return null;
    }

    protected function createMainTask(array $analysis, ?int $executorId): Task
    {
        $dueDate = $this->parseDueDate($analysis);

        return Task::create([
            'title' => $analysis['task_title'] ?? 'Задача по обработке письма',
            'content' => $this->buildTaskContent($analysis),
            'status' => 'new',
            'priority' => $this->mapPriority($analysis['task_priority'] ?? 'medium'),
            'thread_id' => $this->generation->thread_id,
            'executor_id' => $executorId,
            'creator_id' => 1, // Системный пользователь
            'due_date' => $dueDate,
            'metadata' => [
                'generation_id' => $this->generation->id,
                'email_id' => $this->generation->email_id,
                'analysis_type' => 'main_task',
                'department' => $analysis['department'] ?? 'general'
            ]
        ]);
    }

    protected function createFollowUpTasks(array $analysis, ?int $executorId): array
    {
        $taskIds = [];
        $followUpActions = $analysis['action_recommendations']['follow_up_actions'] ?? [];

        foreach ($followUpActions as $action) {
            $task = Task::create([
                'title' => $action,
                'content' => "Follow-up действие: {$action}\n\nКонтекст: " . ($analysis['summary'] ?? ''),
                'status' => 'new',
                'priority' => 'medium',
                'thread_id' => $this->generation->thread_id,
                'executor_id' => $executorId,
                'creator_id' => 1,
                'due_date' => now()->addDays(3), // Через 3 дня
                'metadata' => [
                    'generation_id' => $this->generation->id,
                    'email_id' => $this->generation->email_id,
                    'analysis_type' => 'follow_up',
                    'parent_action' => $action
                ]
            ]);

            $taskIds[] = $task->id;
        }

        return $taskIds;
    }

    protected function createEscalationTask(array $analysis): Task
    {
        $escalationLevel = $analysis['processing_requirements']['escalation_level'] ?? 'department_head';

        // Ищем руководителя для эскалации
        $manager = $this->findManagerForEscalation($analysis['department'] ?? 'general');

        return Task::create([
            'title' => "Эскалация: {$escalationLevel}",
            'content' => $this->buildEscalationContent($analysis),
            'status' => 'new',
            'priority' => 'urgent',
            'thread_id' => $this->generation->thread_id,
            'executor_id' => $manager?->id,
            'creator_id' => 1,
            'due_date' => now()->addDay(), // Завтра
            'metadata' => [
                'generation_id' => $this->generation->id,
                'email_id' => $this->generation->email_id,
                'analysis_type' => 'escalation',
                'escalation_level' => $escalationLevel
            ]
        ]);
    }

    protected function createRiskTask(array $analysis): Task
    {
        $riskLevel = $analysis['processing_requirements']['legal_risks']['risk_level'];

        // Ищем специалиста по рискам
        $riskSpecialist = $this->findRiskSpecialist();

        return Task::create([
            'title' => "Анализ рисков: {$riskLevel}",
            'content' => $this->buildRiskContent($analysis),
            'status' => 'new',
            'priority' => $riskLevel === 'high' ? 'urgent' : 'high',
            'thread_id' => $this->generation->thread_id,
            'executor_id' => $riskSpecialist?->id,
            'creator_id' => 1,
            'due_date' => now()->addDays(2),
            'metadata' => [
                'generation_id' => $this->generation->id,
                'email_id' => $this->generation->email_id,
                'analysis_type' => 'risk_analysis',
                'risk_level' => $riskLevel
            ]
        ]);
    }

    protected function createApprovalTasks(array $analysis): array
    {
        $taskIds = [];
        $approvalDepartments = $analysis['processing_requirements']['approval_departments'] ?? [];

        foreach ($approvalDepartments as $deptCode) {
            // Ищем представителя департамента для согласования
            $approver = $this->findDepartmentApprover($deptCode);

            $task = Task::create([
                'title' => "Согласование от " . Department::where('code', $deptCode)->value('name'),
                'content' => "Требуется согласование от департамента: {$deptCode}\n\n" . ($analysis['summary'] ?? ''),
                'status' => 'new',
                'priority' => 'high',
                'thread_id' => $this->generation->thread_id,
                'executor_id' => $approver?->id,
                'creator_id' => 1,
                'due_date' => now()->addDays(1),
                'metadata' => [
                    'generation_id' => $this->generation->id,
                    'email_id' => $this->generation->email_id,
                    'analysis_type' => 'approval',
                    'department' => $deptCode
                ]
            ]);

            $taskIds[] = $task->id;
        }

        return $taskIds;
    }

    protected function archiveEmail(array $analysis): void
    {
        // Создаем архивную задачу без исполнителя
        Task::create([
            'title' => $analysis['task_title'] ?? 'Архивное письмо',
            'content' => $this->buildArchivedContent($analysis),
            'status' => 'archived',
            'priority' => 'low',
            'thread_id' => $this->generation->thread_id,
            'executor_id' => null, // Доступно всем
            'creator_id' => 1,
            'due_date' => null,
            'metadata' => [
                'generation_id' => $this->generation->id,
                'email_id' => $this->generation->email_id,
                'analysis_type' => 'archived',
                'reason' => ($analysis['spam_check'] ?? 0) === 1 ? 'spam' : 'no_action_required'
            ]
        ]);

        Log::info("Email archived without task creation", [
            'generation_id' => $this->generation->id,
            'reason' => ($analysis['spam_check'] ?? 0) === 1 ? 'spam' : 'no_action_required'
        ]);
    }

    protected function findManagerForEscalation(string $departmentCode): ?User
    {
        return User::whereHas('department', function($query) use ($departmentCode) {
                    $query->where('code', $departmentCode);
                })
                ->where('is_active', true)
                ->where('role', 'manager')
                ->first();
    }

    protected function findEscalationManager(): ?User
    {
        // Ищем любого активного менеджера для эскалации
        return User::where('is_active', true)
                  ->where('role', 'manager')
                  ->first();
    }

    protected function findRiskSpecialist(): ?User
    {
        return User::whereHas('department', function($query) {
                    $query->where('code', 'legal');
                })
                ->where('is_active', true)
                ->whereIn('role', ['manager', 'specialist'])
                ->first();
    }

    protected function findDepartmentApprover(string $departmentCode): ?User
    {
        return User::whereHas('department', function($query) use ($departmentCode) {
                    $query->where('code', $departmentCode);
                })
                ->where('is_active', true)
                ->whereIn('role', ['manager', 'specialist'])
                ->orderBy('last_task_assigned_at')
                ->first();
    }

    protected function parseDueDate(array $analysis): ?Carbon
    {
        // Получаем количество часов из анализа (новый формат)
        $deadlineHours = $analysis['deadline_hours'] ?? $analysis['processing_requirements']['sla_deadline_hours'] ?? null;

        if ($deadlineHours !== null && is_numeric($deadlineHours)) {
            $hours = (int) $deadlineHours;
            
            // Проверяем разумность значения (от 1 часа до 1 года)
            if ($hours > 0 && $hours <= 8760) { // 8760 часов = 365 дней
                return now()->addHours($hours);
            } else {
                Log::warning("Invalid deadline_hours value", [
                    'deadline_hours' => $hours,
                    'generation_id' => $this->generation->id ?? null
                ]);
            }
        }

        // Обратная совместимость: если ИИ вернул старое поле deadline (ISO datetime)
        $oldDeadline = $analysis['deadline'] ?? $analysis['processing_requirements']['sla_deadline'] ?? null;
        if ($oldDeadline && is_string($oldDeadline)) {
            try {
                return Carbon::parse($oldDeadline);
            } catch (\Exception $e) {
                Log::warning("Failed to parse legacy deadline format", [
                    'deadline' => $oldDeadline,
                    'error' => $e->getMessage(),
                    'generation_id' => $this->generation->id ?? null
                ]);
            }
        }

        return null;
    }

    protected function mapPriority(string $aiPriority): string
    {
        $priorityMap = [
            'urgent' => 'urgent',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low'
        ];

        return $priorityMap[$aiPriority] ?? 'medium';
    }

    protected function buildTaskContent(array $analysis): string
    {
        $content = [];

        if (isset($analysis['summary'])) {
            $content[] = "**Краткое содержание:**\n{$analysis['summary']}";
        }

        if (isset($analysis['core_request'])) {
            $content[] = "**Суть запроса:**\n{$analysis['core_request']}";
        }

        if (isset($analysis['key_points']) && is_array($analysis['key_points'])) {
            $content[] = "**Ключевые моменты:**\n" . implode("\n- ", $analysis['key_points']);
        }

        if (isset($analysis['suggested_response'])) {
            $content[] = "**Предлагаемый ответ:**\n{$analysis['suggested_response']}";
        }

        if (isset($analysis['action_recommendations']['immediate_actions'])) {
            $actions = $analysis['action_recommendations']['immediate_actions'];
            if (is_array($actions)) {
                $content[] = "**Немедленные действия:**\n" . implode("\n- ", $actions);
            }
        }

        return implode("\n\n", $content);
    }

    protected function buildEscalationContent(array $analysis): string
    {
        $content = "**ЭСКЛАЦИЯ**\n\n";

        if (isset($analysis['processing_requirements']['escalation_level'])) {
            $content .= "**Уровень эскалации:** {$analysis['processing_requirements']['escalation_level']}\n\n";
        }

        if (isset($analysis['summary'])) {
            $content .= "**Причина эскалации:**\n{$analysis['summary']}\n\n";
        }

        if (isset($analysis['processing_requirements']['legal_risks'])) {
            $risks = $analysis['processing_requirements']['legal_risks'];
            if (isset($risks['risk_factors']) && is_array($risks['risk_factors'])) {
                $content .= "**Связанные риски:**\n" . implode("\n- ", $risks['risk_factors']);
            }
        }

        return $content;
    }

    protected function buildRiskContent(array $analysis): string
    {
        $content = "**АНАЛИЗ РИСКОВ**\n\n";

        $risks = $analysis['processing_requirements']['legal_risks'] ?? [];

        if (isset($risks['risk_level'])) {
            $content .= "**Уровень риска:** {$risks['risk_level']}\n\n";
        }

        if (isset($risks['risk_factors']) && is_array($risks['risk_factors'])) {
            $content .= "**Факторы риска:**\n" . implode("\n- ", $risks['risk_factors']) . "\n\n";
        }

        if (isset($risks['recommended_actions']) && is_array($risks['recommended_actions'])) {
            $content .= "**Рекомендуемые действия:**\n" . implode("\n- ", $risks['recommended_actions']);
        }

        return $content;
    }

    protected function buildArchivedContent(array $analysis): string
    {
        $reason = ($analysis['spam_check'] ?? 0) === 1 ? 'спам' : 'не требует действий';

        $content = "**АРХИВНОЕ ПИСЬМО**\n";
        $content .= "**Причина:** {$reason}\n\n";

        if (isset($analysis['summary'])) {
            $content .= "**Содержание:**\n{$analysis['summary']}";
        }

        return $content;
    }

    public function failed(Throwable $exception): void
    {
        Log::critical("Task creation failed permanently", [
            'generation_id' => $this->generation->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
