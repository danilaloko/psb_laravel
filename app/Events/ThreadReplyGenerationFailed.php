<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThreadReplyGenerationFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $threadId;
    public $status;
    public $errorMessage;

    /**
     * Create a new event instance.
     */
    public function __construct(int $threadId, string $status = 'error', ?string $errorMessage = null)
    {
        $this->threadId = $threadId;
        $this->status = $status;
        $this->errorMessage = $errorMessage;
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
        return 'thread.reply.generation.failed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
            'timestamp' => now()->toISOString(),
        ];
    }
}
