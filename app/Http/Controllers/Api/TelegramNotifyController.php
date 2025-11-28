<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class TelegramNotifyController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'message' => 'required',
        ]);

        $user = User::find($request->user_id);

        if (!$user || !$user->telegram_chat_id) {
            return response()->json(['message' => 'User has no telegram chat id'], 404);
        }

        Http::post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage", [
            'chat_id' => $user->telegram_chat_id,
            'text' => $request->message
        ]);

        return response()->json(['message' => 'sent'], 200);
    }
}
