<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    public function __construct(
        public readonly string $productName,
        public readonly int $remainingQuantity,
        public readonly string $unitLabel,
        public readonly int $threshold,
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
            'title' => 'Low stock alert',
            'message' => "{$this->productName} is running low: {$this->remainingQuantity} {$this->unitLabel} left "
                ."(alert threshold: {$this->threshold}). Restock soon to keep it available.",
            'reference' => $this->productName,
            'url' => route('farmer.products', absolute: false),
            'icon' => 'bi-exclamation-triangle',
            'category' => 'inventory',
        ];
    }
}
