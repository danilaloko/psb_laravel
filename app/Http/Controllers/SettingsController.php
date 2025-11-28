<?php

namespace App\Http\Controllers;

use App\Services\YandexAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function index(YandexAIService $yandexService)
    {
        try {
            $searchIndexes = $yandexService->getSearchIndexes();
        } catch (\Exception $e) {
            Log::error('Failed to load search indexes', [
                'error' => $e->getMessage()
            ]);

            // При ошибке передаем пустой массив
            $searchIndexes = [];
        }

        return view('settings.index', compact('searchIndexes'));
    }
}
