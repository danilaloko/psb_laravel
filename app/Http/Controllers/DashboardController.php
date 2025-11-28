<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        // Базовый запрос для фильтрации
        $baseQuery = Task::query();

        // Для обычных пользователей показывать только их задачи
        if (!$isAdmin) {
            $baseQuery->where('executor_id', $user->id);
        }

        // Создаем клон для подсчета статистики
        $statsQuery = clone $baseQuery;

        // Применяем фильтры к основному запросу
        $query = Task::with(['thread', 'creator', 'executor']);

        // Для обычных пользователей показывать только их задачи
        if (!$isAdmin) {
            $query->where('executor_id', $user->id);
        }

        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
            $statsQuery->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
            $statsQuery->where('priority', $request->priority);
        }

        // Фильтр по исполнителю для админов
        if ($isAdmin && $request->filled('executor_id')) {
            $query->where('executor_id', $request->executor_id);
            $statsQuery->where('executor_id', $request->executor_id);
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Статистика на основе отфильтрованных данных
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'new' => (clone $statsQuery)->where('status', 'new')->count(),
            'in_progress' => (clone $statsQuery)->where('status', 'in_progress')->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        // Список исполнителей для фильтра админов
        $executors = $isAdmin ? \App\Models\User::where('role', 'user')->get() : collect();

        return view('dashboard.index', compact('tasks', 'stats', 'executors', 'isAdmin'));
    }

    public function show(Task $task)
    {
        $user = Auth::user();

        // Обычные пользователи могут видеть только свои задачи
        if (!$user->isAdmin() && $task->executor_id !== $user->id) {
            abort(403);
        }

        $task->load(['thread', 'creator', 'executor', 'thread.emails']);

        return view('dashboard.show', compact('task'));
    }

    public function updateStatus(Request $request, Task $task)
    {
        $user = Auth::user();

        // Обычные пользователи могут обновлять только свои задачи
        if (!$user->isAdmin() && $task->executor_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:new,in_progress,completed,archived,cancelled',
        ]);

        $task->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Статус задачи обновлен');
    }

    public function showEmail(Email $email)
    {
        $user = Auth::user();

        // Проверяем доступ к письму через задачу
        $task = Task::whereHas('thread', function($query) use ($email) {
            $query->where('id', $email->thread_id);
        })->where('executor_id', $user->id)->first();

        // Обычные пользователи могут видеть только письма из своих задач
        if (!$user->isAdmin() && !$task) {
            abort(403);
        }

        $email->load(['thread']);

        return view('dashboard.email', compact('email'));
    }
}
