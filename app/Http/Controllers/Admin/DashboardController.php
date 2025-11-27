<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return view('admin.dashboard', compact(
            'totalTasks',
            'totalUsers',
            'newTasks',
            'completedTasks',
            'statusStats',
            'priorityStats',
            'tasksByDay',
            'topUsers',
            'recentTasks'
        ));
    }
}
