<?php

namespace App\Services;

use Telegram\Bot\Api;

class TelegramService
{
    protected Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('services.telegram.bot_token'));
    }

    public function sendMessage(int|string $chatId, string $text)
    {
        return $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}
