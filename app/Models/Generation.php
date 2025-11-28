<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    protected $fillable = [
        'email_id', 'thread_id', 'type', 'prompt', 'response', 'processing_time',
        'status', 'error_message', 'metadata'
    ];

    protected $casts = [
        'response' => 'array',
        'metadata' => 'array',
        'processing_time' => 'decimal:3'
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    // Helper методы для доступа к metadata
    public function getModelName()
    {
        return $this->metadata['model']['name'] ?? null;
    }

    public function getTotalTokens()
    {
        return $this->metadata['tokens']['total'] ?? null;
    }

    public function getCost()
    {
        return $this->metadata['cost']['amount'] ?? null;
    }

    // Методы для статистики
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeByModel($query, $model)
    {
        return $query->whereJsonContains('metadata->model->name', $model);
    }

    public function scopeTotalCost($query)
    {
        return $query->where('status', 'success')
                    ->selectRaw('SUM(JSON_EXTRACT(metadata, "$.cost.amount")) as total_cost')
                    ->value('total_cost');
    }

    public function scopeReplies($query)
    {
        return $query->where('type', 'reply');
    }

    public function scopeAnalyses($query)
    {
        return $query->where('type', 'analysis');
    }

    public function scopeByThread($query, $threadId)
    {
        return $query->where('thread_id', $threadId);
    }
}
