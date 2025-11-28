<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessEmailWithAI;
use App\Models\Email;
use App\Models\Thread;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    // Метод для создания нового email (например, через API или форму)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'thread_id' => 'nullable|exists:threads,id',
            'from_address' => 'required|email',
            'from_name' => 'nullable|string|max:255',
        ]);

        // Создаем или находим thread
        $thread = Thread::firstOrCreate([
            'title' => $validated['subject'] ?? 'Новый тред'
        ]);

        // Создаем email
        $email = Email::create([
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'thread_id' => $thread->id,
            'from_address' => $validated['from_address'],
            'from_name' => $validated['from_name'] ?? null,
            'received_at' => now(),
        ]);

        // 🔥 ЗАПУСКАЕМ JOB СРАЗУ ПОСЛЕ СОЗДАНИЯ EMAIL
        ProcessEmailWithAI::dispatch($email);

        return response()->json([
            'message' => 'Email создан и отправлен на обработку ИИ',
            'email_id' => $email->id
        ], 201);
    }

    // Метод для обработки IMAP входящих писем
    public function processIncoming(Request $request)
    {
        $minutes = $request->input('minutes', 60);

        try {
            $fetcher = new \App\Services\EmailFetcherService();
            $result = $fetcher->fetchRecentEmails($minutes);

            if (is_array($result)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Обработка писем завершена',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении писем'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка: ' . $e->getMessage()
            ], 500);
        }
    }
}
