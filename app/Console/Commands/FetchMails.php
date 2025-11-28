<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class FetchMails extends Command
{
    protected $signature = 'mail:fetch';
    protected $description = 'Fetch emails from IMAP server';

    public function handle()
    {
        // Создаём клиента по конфигу "default"
        $client = Client::account('default');

        // Подключаемся
        $client->connect();

        // Берём INBOX
        $folder = $client->getFolder('INBOX');

        // Можно ограничить количество, например последние 50
        $messages = $folder->query()->since(now()->subDays(7))->limit(50)->get();

        foreach ($messages as $message) {
            $this->info('---');
            $this->line('ID: ' . $message->getMessageId());
            $this->line('Дата: ' . $message->getDate());
            $this->line('От: ' . $message->getFrom()[0]->mail . ' (' . $message->getFrom()[0]->personal . ')');
            $this->line('Кому: ' . ($message->getTo()[0]->mail ?? ''));
            $this->line('Тема: ' . $message->getSubject());
            $this->line('Сниппет: ' . mb_substr($message->getTextBody(), 0, 200));
        }

        $client->disconnect();

        return Command::SUCCESS;
    }
}
