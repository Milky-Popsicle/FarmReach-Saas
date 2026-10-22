<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class OrderCancelledNotification extends Notification
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $cancelledBy,
        public readonly string $counterpartName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && $notifiable->isAdmin() ? [] : ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        if ($this->cancelledBy === 'buyer') {
            $message = "{$this->counterpartName} cancelled order {$this->orderNumber}. The reserved stock has been returned to your inventory.";
            $url = route('farmer.orders', absolute: false);
        } else {
            $message = "{$this->counterpartName} cancelled order {$this->orderNumber}. You no longer need to prepare this order.";
            $url = route('buyer.orders', absolute: false);
        }

        return [
            'title' => 'Order cancelled',
            'message' => $message,
            'reference' => $this->orderNumber,
            'url' => $url,
            'icon' => 'bi-x-circle',
            'category' => 'order',
        ];
    }
}
