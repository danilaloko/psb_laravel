<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailWithAI;
use App\Models\Email;
use App\Models\Generation;
use Illuminate\Http\Request;

class AIAnalysisController extends Controller
{
    // Метод для ручной обработки существующего email
    public function processEmail(Email $email)
    {
        ProcessEmailWithAI::dispatch($email);

        return response()->json([
            'message' => 'Email отправлен на обработку ИИ',
            'email_id' => $email->id
        ]);
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
