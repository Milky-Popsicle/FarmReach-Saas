<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_type' => $this->order_type,
            'total_amount' => (float) $this->total_amount,
            'delivery_method' => $this->delivery_method,
            'delivery_address' => $this->delivery_address,
            'shipping_fee' => (float) $this->shipping_fee,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'buyer' => $this->whenLoaded('buyer', fn (): ?array => $this->buyer === null ? null : [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'phone_number' => $this->buyer->phone_number,
                'address' => $this->buyer->address,
            ]),
            'farmer' => $this->whenLoaded('farmer', fn (): ?array => $this->farmer === null ? null : [
                'id' => $this->farmer->id,
                'name' => $this->farmer->name,
                'phone_number' => $this->farmer->phone_number,
                'address' => $this->farmer->address,
            ]),
            'items' => $this->whenLoaded('items', fn (): array => $this->items
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'product' => $item->product === null ? null : [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'price' => (float) $item->product->price,
                        'unit' => $item->product->unit,
                        'image_path' => $item->product->image_path,
                    ],
                ])
                ->all()),
            'payment' => $this->whenLoaded('payment', fn (): ?array => $this->payment === null ? null : [
                'payment_method' => $this->payment->payment_method,
                'payment_status' => $this->payment->payment_status,
                'reference_number' => $this->payment->reference_number,
                'amount' => (float) $this->payment->amount,
                'payment_date' => $this->payment->payment_date?->toIso8601String(),
            ]),
        ];
    }
}
