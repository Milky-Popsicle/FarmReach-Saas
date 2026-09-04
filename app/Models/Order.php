<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'buyer_id',
        'farmer_id',
        'order_type',
        'total_amount',
        'delivery_method',
        'delivery_address',
        'shipping_fee',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function isBulk(): bool
    {
        return $this->order_type === 'bulk';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'confirmed' => 'badge-confirmed',
            'delivered' => 'badge-delivered',
            'cancelled' => 'badge-cancelled',
            default => 'badge-pending',
        };
    }
}
