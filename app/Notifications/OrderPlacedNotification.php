<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $buyerName,
        public readonly float $totalAmount,
        public readonly int $itemCount,
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
        return [
            'title' => 'New order received',
            'message' => "{$this->buyerName} placed order {$this->orderNumber} for ₱".number_format($this->totalAmount, 2)
                ." ({$this->itemCount} ".($this->itemCount === 1 ? 'item' : 'items').'). Review and confirm it.',
            'reference' => $this->orderNumber,
            'url' => route('farmer.orders', absolute: false),
            'icon' => 'bi-bag-check',
            'category' => 'order',
        ];
    }
}
