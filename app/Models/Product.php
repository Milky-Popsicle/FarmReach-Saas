<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'description',
        'price',
        'quantity',
        'kilograms',
        'unit',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'kilograms' => 'decimal:2',
            'quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->quantity <= 0) {
            return 'Out of Stock';
        }
        if ($this->quantity <= 10) {
            return 'Low Stock';
        }
        return 'In Stock';
    }
}
