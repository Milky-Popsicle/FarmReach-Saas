<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $audience,
        public readonly string $farmerName,
        public readonly string $buyerName,
        public readonly ?float $shippingFee = null,
        public readonly ?float $totalAmount = null,
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
        $completed = in_array($this->status, Order::COMPLETED_STATUSES, true);

        if ($this->audience === 'farmer') {
            $title = 'Order completed';
            $message = "Order {$this->orderNumber} from {$this->buyerName} was marked as {$this->statusLabel}.";
            $url = route('farmer.orders', absolute: false);
        } else {
            $title = $completed ? "Order {$this->statusLabel}" : 'Order confirmed';
            $message = $completed
                ? "Order {$this->orderNumber} from {$this->farmerName} was marked as {$this->statusLabel}. Thank you for buying local!"
                : "{$this->farmerName} confirmed order {$this->orderNumber}. It is now being prepared.";
            $url = route('buyer.orders', absolute: false);

            if (! $completed && $this->shippingFee !== null && $this->totalAmount !== null) {
                $message .= ' Shipping fee: ₱'.number_format($this->shippingFee, 2)
                    .'. Updated total: ₱'.number_format($this->totalAmount, 2).'.';
            }
        }

        return [
            'title' => $title,
            'message' => $message,
            'reference' => $this->orderNumber,
            'url' => $url,
            'icon' => $completed ? 'bi-bag-check' : 'bi-check2-circle',
            'category' => 'order',
        ];
    }
}
