<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailWithAI;
use App\Models\Email;
use App\Models\Task;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function create()
    {
        return view('tasks.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'email_subject' => 'required|string|max:255',
            'content' => 'required|string',
            'from_address' => 'required|email',
            'from_name' => 'nullable|string|max:255',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:new,in_progress,completed,archived,cancelled',
            'due_date' => 'nullable|date|after:now',
        ]);

        $user = Auth::user();

        $task = DB::transaction(function () use ($validated, $user) {
            // Создаем thread для задачи
            $thread = Thread::create([
                'title' => $validated['title'],
                'status' => 'active',
            ]);

            // Создаем задачу
            $task = Task::create([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'status' => $validated['status'],
                'priority' => $validated['priority'],
                'thread_id' => $thread->id,
                'executor_id' => $user->id, // на себя
                'creator_id' => $user->id,
                'due_date' => $validated['due_date'] ?? null,
            ]);

            // Создаем email
            $email = Email::create([
                'subject' => $validated['email_subject'],
                'content' => $validated['content'],
                'thread_id' => $thread->id,
                'from_address' => $validated['from_address'],
                'from_name' => $validated['from_name'] ?? null,
                'received_at' => now(),
            ]);

            // 🔥 ЗАПУСКАЕМ JOB СРАЗУ ПОСЛЕ СОЗДАНИЯ EMAIL
            ProcessEmailWithAI::dispatch($email);

            return $task;
        });

        return redirect()->route('dashboard.task.show', $task)->with('success', 'Задача создана успешно');
    }
}
