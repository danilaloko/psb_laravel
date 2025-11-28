<?php

namespace App\Events;

use App\Models\Generation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadReplyGenerationCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $threadId;
    public $status;
    public $generationData;

    /**
     * Create a new event instance.
     */
    public function __construct(int $threadId, string $status = 'completed', ?array $generationData = null)
    {
        $this->threadId = $threadId;
        $this->status = $status;
        $this->generationData = $generationData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('thread.' . $this->threadId . '.reply'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'thread.reply.generation.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'status' => $this->status,
            'generation' => $this->generationData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
