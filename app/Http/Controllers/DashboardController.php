<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Task::with(['thread', 'creator'])->where('executor_id', $user->id);

        // Фильтры
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && $request->priority !== '') {
            $query->where('priority', $request->priority);
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(15);

        $stats = [
            'total' => Task::where('executor_id', $user->id)->count(),
            'new' => Task::where('executor_id', $user->id)->where('status', 'new')->count(),
            'in_progress' => Task::where('executor_id', $user->id)->where('status', 'in_progress')->count(),
            'completed' => Task::where('executor_id', $user->id)->where('status', 'completed')->count(),
            'cancelled' => Task::where('executor_id', $user->id)->where('status', 'cancelled')->count(),
        ];

        return view('dashboard.index', compact('tasks', 'stats'));
    }

    public function show(Task $task)
    {
        if ($task->executor_id !== Auth::id()) {
            abort(403);
        }

        $task->load(['thread', 'creator', 'thread.emails']);

        return view('dashboard.show', compact('task'));
    }

    public function updateStatus(Request $request, Task $task)
    {
        if ($task->executor_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:new,in_progress,completed,archived,cancelled',
        ]);

        $task->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Статус задачи обновлен');
    }
}
