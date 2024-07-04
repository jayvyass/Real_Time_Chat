<?php
namespace App\Events;

use App\Models\ChatMessage;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatMessage $message)
    {
        $createdAt = Carbon::parse($message->created_at)->setTimezone('Asia/Kolkata');
        if ($createdAt->isYesterday()) {
            $message->formatted_time = $createdAt->format('D'); // Day of the week in three letters
        } else {
            $message->formatted_time = $createdAt->format('H:i'); // Time in HH:mm format
        }

        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->message->receiver_id}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'mstatus' => $this->message->mstatus,
        ];
    }
}
