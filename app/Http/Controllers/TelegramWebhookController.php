<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Telegram\Bot\Api;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $telegram = new Api(config('services.telegram.bot_token'));

        $update = $telegram->getWebhookUpdate();

        // Если пришло сообщение
        if ($update->message) {
            $chatId = $update->message->chat->id;
            $text   = trim($update->message->text);

            // Стартовая команда
            if ($text === '/start') {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Привет! Для авторизации отправь:\n\n/login ВАШ_EMAIL ВАШ_ПАРОЛЬ"
                ]);
                return;
            }

            // Команда /login email password
            if (str_starts_with($text, '/login ')) {

                $parts = explode(' ', $text);

                if (count($parts) !== 3) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Неверный формат. Используй:\n/login email password"
                    ]);
                    return;
                }

                [$_, $email, $password] = $parts;

                // Попытаться найти пользователя по почте
                $user = User::where('email', $email)->first();

                if (!$user || !password_verify($password, $user->password)) {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Неверный логин или пароль."
                    ]);
                    return;
                }

                // Сохранить telegram id
                $user->telegram_chat_id = $chatId;
                $user->save();

                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Успешная авторизация! Теперь вы будете получать уведомления."
                ]);

                return;
            }

            // Любое другое сообщение
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "Я не понял команду. Используй /start"
            ]);
        }
    }
}
