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

    public function generateReply(Request $request, Task $task)
    {
        // Проверяем права доступа (админы имеют доступ ко всем задачам)
        $user = Auth::user();
        if (!$user->isAdmin() && $task->executor_id !== $user->id && $task->creator_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к этой задаче'
            ], 403);
        }

        // Проверяем наличие thread
        if (!$task->thread) {
            return response()->json([
                'success' => false,
                'message' => 'У задачи нет потока для генерации ответа'
            ], 400);
        }

        // Проверяем наличие писем в thread
        if ($task->thread->emails()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'В потоке задачи нет писем для генерации ответа'
            ], 400);
        }

        try {
            // Загружаем thread с отношениями перед dispatch для корректной сериализации
            $thread = $task->thread()->with('emails')->firstOrFail();
            
            // Запускаем генерацию ответа
            \App\Jobs\GenerateThreadReply::dispatch($thread);

            return response()->json([
                'success' => true,
                'message' => 'Генерация ответа запущена'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to start reply generation for task {$task->id}", [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при запуске генерации ответа'
            ], 500);
        }
    }

    public function getReplyStatus(Task $task)
    {
        // Проверяем права доступа (админы имеют доступ ко всем задачам)
        $user = Auth::user();
        if (!$user->isAdmin() && $task->executor_id !== $user->id && $task->creator_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Нет доступа'
            ], 403);
        }

        // Проверяем наличие thread
        if (!$task->thread) {
            return response()->json([
                'status' => 'no_thread',
                'message' => 'У задачи нет потока'
            ]);
        }

        // Находим последнюю генерацию ответа для thread
        $latestReply = $task->thread->getLatestReplyGeneration();

        if (!$latestReply) {
            return response()->json([
                'status' => 'not_started',
                'message' => 'Генерация не запускалась'
            ]);
        }

        // Преобразуем статус из БД в статус для фронтенда
        $isRecent = $latestReply->created_at->isAfter(now()->subMinutes(5));
        $frontendStatus = $latestReply->status === 'success'
            ? 'completed'
            : ($isRecent && $latestReply->status !== 'error' ? 'processing' : $latestReply->status);

        $response = [
            'status' => $frontendStatus,
            'created_at' => $latestReply->created_at->toISOString(),
        ];

        // Показываем данные генерации для последней генерации, если она успешна
        if ($latestReply->status === 'success' && $latestReply->response && is_array($latestReply->response)) {
            $response['reply'] = [
                'text' => $latestReply->response['reply'] ?? '',
                'processing_time' => $latestReply->processing_time,
                'cost' => $latestReply->getCost(),
                'model' => $latestReply->getModelName(),
                'tokens' => $latestReply->getTotalTokens(),
            ];
        }

        // Если есть ошибка, показываем сообщение
        if ($latestReply->status === 'error' && $latestReply->error_message) {
            $response['error_message'] = $latestReply->error_message;
        }

        return response()->json($response);
    }
}
