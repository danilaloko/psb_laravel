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
}
