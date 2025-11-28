<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Проверка прав администратора
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }
        
        // Общая статистика
        $totalTasks = Task::count();
        $totalUsers = User::where('role', 'user')->count();
        $newTasks = Task::where('status', 'new')->count();
        $completedTasks = Task::where('status', 'completed')->count();

        // Статистика по статусам
        $statusStats = Task::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Добавить cancelled статус если его нет
        if (!isset($statusStats['cancelled'])) {
            $statusStats['cancelled'] = 0;
        }

        // Статистика по приоритетам
        $priorityStats = Task::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        // Добавить urgent приоритет если его нет
        if (!isset($priorityStats['urgent'])) {
            $priorityStats['urgent'] = 0;
        }

        // Задачи по дням (последние 30 дней)
        $tasksByDay = Task::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Топ пользователей по количеству задач
        $topUsers = User::withCount('assignedTasks')
            ->where('role', 'user')
            ->orderBy('assigned_tasks_count', 'desc')
            ->limit(5)
            ->get();

        // Последние задачи
        $recentTasks = Task::with(['executor', 'creator', 'thread'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Просроченные задачи (список)
        $overdueTasksList = Task::where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->with(['executor', 'creator', 'thread'])
            ->orderBy('due_date', 'asc')
            ->get();

        // Активные задачи (в работе)
        $activeTasks = Task::whereIn('status', ['new', 'in_progress'])->count();

        // ========== AI МЕТРИКИ ==========
        
        $totalGenerations = Generation::count();
        $successfulGenerations = Generation::where('status', 'success')->count();
        $failedGenerations = Generation::where('status', 'error')->count();
        $aiSuccessRate = $totalGenerations > 0 
            ? round(($successfulGenerations / $totalGenerations) * 100, 1) 
            : 0;

        // Среднее время генерации
        $avgGenerationTime = Generation::where('status', 'success')
            ->whereNotNull('processing_time')
            ->avg('processing_time');
        $avgGenerationTime = $avgGenerationTime ? round($avgGenerationTime, 2) : 0;

        // Общая стоимость AI
        $totalAICost = Generation::where('status', 'success')
            ->get()
            ->sum(function ($generation) {
                return $generation->getCost() ?? 0;
            });
        $totalAICost = round($totalAICost, 2);

        // Статистика по типам генераций
        $generationsByType = Generation::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Статистика по моделям
        $generationsByModel = Generation::where('status', 'success')
            ->get()
            ->groupBy(function ($generation) {
                return $generation->getModelName() ?? 'unknown';
            })
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'avg_time' => round($group->avg('processing_time') ?? 0, 2),
                    'total_cost' => round($group->sum(function ($g) { return $g->getCost() ?? 0; }), 2)
                ];
            })
            ->toArray();

        return view('admin.dashboard', compact(
            'totalTasks',
            'totalUsers',
            'newTasks',
            'completedTasks',
            'statusStats',
            'priorityStats',
            'tasksByDay',
            'topUsers',
            'recentTasks',
            'overdueTasksList',
            'activeTasks',
            // AI метрики
            'totalGenerations',
            'successfulGenerations',
            'failedGenerations',
            'aiSuccessRate',
            'avgGenerationTime',
            'totalAICost',
            'generationsByType',
            'generationsByModel'
        ));
    }
}
