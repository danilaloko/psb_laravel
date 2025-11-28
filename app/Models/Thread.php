<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    protected $fillable = [
        'title',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function getLatestReplyGeneration()
    {
        return $this->generations()->replies()->latest()->first();
    }

    public function hasReplyGeneration(): bool
    {
        return $this->generations()->replies()->exists();
    }

    public function getThreadContext(): string
    {
        // Используем уже загруженные emails если они есть, иначе загружаем
        if ($this->relationLoaded('emails')) {
            $emails = $this->emails->sortBy('received_at');
        } else {
            $emails = $this->emails()->orderBy('received_at')->get();
        }

        $context = [];
        foreach ($emails as $email) {
            $direction = $email->from_address ? 'Входящее' : 'Исходящее';
            $context[] = sprintf(
                "[%s] %s <%s> - %s\nТема: %s\n%s",
                $email->received_at?->format('d.m.Y H:i'),
                $email->from_name ?? 'Неизвестно',
                $email->from_address ?? 'неизвестно',
                $direction,
                $email->subject,
                $email->content
            );
        }

        return implode("\n\n---\n\n", $context);
    }
}
