<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TelegramAuthController extends Controller
{
    public function auth(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required',
            'telegram_chat_id' => 'required',
        ]);

        $user = User::where('email', $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user->telegram_chat_id = $request->telegram_chat_id;
        $user->save();

        return response()->json(['message' => 'authorized'], 200);
    }
}
