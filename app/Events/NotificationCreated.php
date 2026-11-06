<?php

namespace App\Events;

use App\Models\User;
use App\Notifications\NotificationPayload;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public DatabaseNotification $notification,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->user->getKey());
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return NotificationPayload::for($this->notification);
    }
}
