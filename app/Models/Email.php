<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Email extends Model
{
    protected $fillable = [
        'subject',
        'content',
        'thread_id',
        'from_address',
        'from_name',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }
}
