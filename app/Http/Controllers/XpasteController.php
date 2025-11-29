<?php

namespace App\Http\Controllers;

use App\Services\XpasteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XpasteController extends Controller
{
    protected XpasteService $xpasteService;

    public function __construct(XpasteService $xpasteService)
    {
        $this->xpasteService = $xpasteService;
    }

    /**
     * Создать заметку в xpaste
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:524288', // 512 KB
            'language' => 'nullable|string|in:text,markdown',
            'auto_destroy' => 'nullable|boolean',
            'ttl_days' => 'nullable|integer|min:1|max:3650', // Максимум 10 лет
        ]);

        try {
            $url = $this->xpasteService->createPaste(
                body: $validated['body'],
                language: $validated['language'] ?? 'text',
                autoDestroy: $validated['auto_destroy'] ?? false,
                ttlDays: $validated['ttl_days'] ?? 365
            );

            return response()->json([
                'success' => true,
                'message' => 'Заметка успешно создана',
                'url' => $url,
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to create xpaste note', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании заметки: ' . $e->getMessage(),
            ], 500);
        }
    }
}

