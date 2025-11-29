<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Email;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();
        $isDepartmentAdmin = $user->isDepartmentAdmin();

        // Базовый запрос для фильтрации с учетом прав доступа
        $baseQuery = Task::query();

        if ($isAdmin) {
            // Полный админ видит все задачи
            // Базовый запрос остается без ограничений
        } elseif ($isDepartmentAdmin) {
            // Админ подразделения видит все задачи своего подразделения
            $baseQuery->whereHas('executor', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        } else {
            // Обычный пользователь видит только свои задачи
            $baseQuery->where('executor_id', $user->id);
        }

        // Создаем клон для подсчета статистики
        $statsQuery = clone $baseQuery;

        // Применяем фильтры к основному запросу
        $query = Task::with(['thread', 'creator', 'executor.department']);

        // Применяем те же ограничения доступа к основному запросу
        if ($isAdmin) {
            // Полный админ видит все задачи
        } elseif ($isDepartmentAdmin) {
            // Админ подразделения видит все задачи своего подразделения
            $query->whereHas('executor', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        } else {
            // Обычный пользователь видит только свои задачи
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

        // Фильтр по исполнителю для админов и админов подразделений
        if (($isAdmin || $isDepartmentAdmin) && $request->filled('executor_id')) {
            $query->where('executor_id', $request->executor_id);
            $statsQuery->where('executor_id', $request->executor_id);
        }

        // Фильтр по подразделению
        if ($request->filled('department_id')) {
            $query->whereHas('executor', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
            $statsQuery->whereHas('executor', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
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

        // Список исполнителей для фильтра админов и админов подразделений
        if ($isAdmin) {
            $executors = \App\Models\User::where('role', 'user')->get();
        } elseif ($isDepartmentAdmin) {
            // Админ подразделения видит только пользователей своего подразделения
            $executors = \App\Models\User::where('department_id', $user->department_id)
                ->where('role', 'user')
                ->get();
        } else {
            $executors = collect();
        }

        // Список подразделений для фильтра
        if ($isAdmin) {
            $departments = Department::active()->get();
        } elseif ($isDepartmentAdmin) {
            // Админ подразделения видит только свое подразделение
            $departments = $user->department_id 
                ? Department::where('id', $user->department_id)->get() 
                : collect();
        } else {
            // Для обычных пользователей показываем только их подразделение
            $departments = $user->department_id 
                ? Department::where('id', $user->department_id)->get() 
                : collect();
        }

        return view('dashboard.index', compact('tasks', 'stats', 'executors', 'departments', 'isAdmin'));
    }

    public function show(Task $task)
    {
        $user = Auth::user();

        // Загружаем executor для проверки доступа
        $task->load('executor');

        // Проверка доступа к задаче
        if ($user->isAdmin()) {
            // Полный админ видит все задачи
        } elseif ($user->isDepartmentAdmin()) {
            // Админ подразделения видит задачи своего подразделения
            if (!$task->executor || $task->executor->department_id !== $user->department_id) {
                abort(403);
            }
        } else {
            // Обычный пользователь видит только свои задачи
            if ($task->executor_id !== $user->id) {
                abort(403);
            }
        }

        $task->load(['thread', 'creator', 'thread.emails']);

        return view('dashboard.show', compact('task'));
    }

    public function updateStatus(Request $request, Task $task)
    {
        $user = Auth::user();

        // Загружаем executor для проверки доступа
        $task->load('executor');

        // Проверка доступа к задаче
        if ($user->isAdmin()) {
            // Полный админ может обновлять все задачи
        } elseif ($user->isDepartmentAdmin()) {
            // Админ подразделения может обновлять задачи своего подразделения
            if (!$task->executor || $task->executor->department_id !== $user->department_id) {
                abort(403);
            }
        } else {
            // Обычный пользователь может обновлять только свои задачи
            if ($task->executor_id !== $user->id) {
                abort(403);
            }
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
        if ($user->isAdmin()) {
            // Полный админ видит все письма
            $task = Task::whereHas('thread', function($query) use ($email) {
                $query->where('id', $email->thread_id);
            })->first();
        } elseif ($user->isDepartmentAdmin()) {
            // Админ подразделения видит письма из задач своего подразделения
            $task = Task::whereHas('thread', function($query) use ($email) {
                $query->where('id', $email->thread_id);
            })->whereHas('executor', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            })->first();
        } else {
            // Обычный пользователь видит только письма из своих задач
            $task = Task::whereHas('thread', function($query) use ($email) {
                $query->where('id', $email->thread_id);
            })->where('executor_id', $user->id)->first();
        }

        if (!$task) {
            abort(403);
        }

        $email->load(['thread']);

        return view('dashboard.email', compact('email'));
    }
}
