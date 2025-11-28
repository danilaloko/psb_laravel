<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailWithAI;
use App\Models\Email;
use App\Models\Generation;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AIAnalysisController extends Controller
{
    // Метод для ручной обработки существующего email
    public function processEmail(Email $email)
    {
        // Проверяем права доступа (админы имеют доступ ко всем письмам)
        $user = Auth::user();
        if (!$user->isAdmin()) {
            // Обычные пользователи могут анализировать только письма из своих задач
            $task = Task::whereHas('thread', function($query) use ($email) {
                $query->where('id', $email->thread_id);
            })->where('executor_id', $user->id)->first();

            if (!$task) {
                abort(403, 'У вас нет доступа к этому письму');
            }
        }

        // Всегда запускаем новый анализ, независимо от существующих
        ProcessEmailWithAI::dispatch($email);

        return response()->json([
            'success' => true,
            'message' => 'Анализ запущен',
            'email_id' => $email->id
        ]);
    }

    // Получить статус анализа для email (для polling)
    public function getAnalysisStatus(Email $email)
    {
        // Проверяем права доступа (админы имеют доступ ко всем письмам)
        $user = Auth::user();
        if (!$user->isAdmin()) {
            // Обычные пользователи могут видеть только письма из своих задач
            $task = Task::whereHas('thread', function($query) use ($email) {
                $query->where('id', $email->thread_id);
            })->where('executor_id', $user->id)->first();

            if (!$task) {
                abort(403, 'У вас нет доступа к этому письму');
            }
        }

        // Находим последнюю генерацию для этого email (по времени создания)
        $latestGeneration = Generation::where('email_id', $email->id)
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

    // Показать результаты AI анализа для email
    public function showAnalysis(Email $email)
    {
        $email->load(['thread', 'generations' => function($query) {
            $query->latest()->first(); // Последняя генерация
        }]);

        $latestGeneration = $email->generations->first();

        return response()->json([
            'email' => [
                'id' => $email->id,
                'subject' => $email->subject,
                'content' => $email->content,
                'from_name' => $email->from_name,
                'from_address' => $email->from_address,
                'received_at' => $email->received_at,
            ],
            'ai_analysis' => $latestGeneration?->response,
            'processing_status' => $latestGeneration?->status ?? 'not_processed',
            'processed_at' => $latestGeneration?->created_at,
            'processing_time' => $latestGeneration?->processing_time,
            'model_used' => $latestGeneration?->getModelName(),
            'tokens_used' => $latestGeneration?->getTotalTokens(),
            'cost' => $latestGeneration?->getCost(),
        ]);
    }

    // Получить все генерации для email
    public function getAllGenerations(Email $email)
    {
        $generations = Generation::where('email_id', $email->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'email_id' => $email->id,
            'total_generations' => $generations->count(),
            'generations' => $generations->map(function ($generation) {
                return [
                    'id' => $generation->id,
                    'created_at' => $generation->created_at,
                    'status' => $generation->status,
                    'processing_time' => $generation->processing_time,
                    'model' => $generation->getModelName(),
                    'tokens' => $generation->getTotalTokens(),
                    'cost' => $generation->getCost(),
                    'analysis' => $generation->response,
                ];
            })
        ]);
    }

    // Статистика по генерациям
    public function getStats()
    {
        $stats = [
            'total_generations' => Generation::count(),
            'successful_generations' => Generation::successful()->count(),
            'failed_generations' => Generation::where('status', 'error')->count(),
            'total_cost' => Generation::successful()->sum('metadata->cost->amount'),
            'average_processing_time' => Generation::successful()->avg('processing_time'),
            'total_tokens' => Generation::successful()->sum('metadata->tokens->total'),
        ];

        return response()->json($stats);
    }
}
