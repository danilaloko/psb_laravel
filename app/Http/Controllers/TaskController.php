<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailWithAI;
use App\Models\Email;
use App\Models\Generation;
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

    public function analyzeLatestEmail(Task $task)
    {
        // Проверяем права доступа (админы имеют доступ ко всем задачам)
        $user = Auth::user();
        if (!$user->isAdmin() && $task->executor_id !== $user->id && $task->creator_id !== $user->id) {
            abort(403, 'У вас нет доступа к этой задаче');
        }

        // Находим последний email в thread задачи
        $latestEmail = $task->thread->emails()->latest('received_at')->first();

        if (!$latestEmail) {
            return response()->json([
                'success' => false,
                'message' => 'В задаче нет писем для анализа'
            ], 400);
        }

        // Всегда запускаем новый анализ, независимо от существующих
        ProcessEmailWithAI::dispatch($latestEmail);

        return response()->json([
            'success' => true,
            'message' => 'Анализ запущен',
            'email_id' => $latestEmail->id
        ]);
    }

    public function getAnalysisStatus(Task $task)
    {
        // Проверяем права доступа (админы имеют доступ ко всем задачам)
        $user = Auth::user();
        if (!$user->isAdmin() && $task->executor_id !== $user->id && $task->creator_id !== $user->id) {
            abort(403, 'У вас нет доступа к этой задаче');
        }

        // Проверяем наличие thread
        if (!$task->thread) {
            return response()->json([
                'status' => 'no_emails',
                'message' => 'У задачи нет потока'
            ]);
        }

        // Находим последний email в thread задачи
        $latestEmail = $task->thread->emails()->latest('received_at')->first();

        if (!$latestEmail) {
            return response()->json([
                'status' => 'no_emails',
                'message' => 'В задаче нет писем'
            ]);
        }

        // Находим последнюю генерацию для этого email (по времени создания)
        $latestGeneration = Generation::where('email_id', $latestEmail->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestGeneration) {
            return response()->json([
                'status' => 'not_started',
                'message' => 'Анализ не запускался'
            ]);
        }

        // Преобразуем статус из БД в статус для фронтенда
        // Если генерация создана недавно (менее 5 минут назад) и статус не success, значит анализ выполняется
        $isRecent = $latestGeneration->created_at->isAfter(now()->subMinutes(5));
        $frontendStatus = $latestGeneration->status === 'success' 
            ? 'completed' 
            : ($isRecent && $latestGeneration->status !== 'error' ? 'processing' : $latestGeneration->status);
        
        $response = [
            'status' => $frontendStatus,
            'created_at' => $latestGeneration->created_at->toISOString(),
        ];

        // Показываем данные анализа для последней генерации, если они есть (независимо от статуса)
        if ($latestGeneration->response && is_array($latestGeneration->response)) {
            $response['analysis'] = [
                'summary' => $latestGeneration->response['summary'] ?? '',
                'priority' => $latestGeneration->response['priority'] ?? 'medium',
                'category' => $latestGeneration->response['category'] ?? '',
                'sentiment' => $latestGeneration->response['sentiment'] ?? 'neutral',
                'action_required' => $latestGeneration->response['action_required'] ?? false,
                'suggested_response' => $latestGeneration->response['suggested_response'] ?? '',
                'processing_time' => $latestGeneration->processing_time,
                'cost' => $latestGeneration->getCost(),
                'model' => $latestGeneration->getModelName(),
                'tokens' => $latestGeneration->getTotalTokens(),
            ];
        }

        return response()->json($response);
    }
}
