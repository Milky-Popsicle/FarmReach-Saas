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

    protected $appends = [
        'stock_status',
        'stock_with_unit',
        'price_unit_label',
        'stock_unit_label',
        'has_weight_per_unit',
        'formatted_weight_per_unit',
        'formatted_price_per_unit',
        'selling_unit',
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

    public const SELLING_UNITS = [
        'kg',
        'g',
        'pcs',
        'bundle',
        'pack',
        'sack',
        'crate',
        'box',
    ];

    public const CATEGORY_SUGGESTED_UNITS = [
        'vegetables' => ['kg', 'pcs', 'bundle', 'crate', 'pack'],
        'fruits' => ['kg', 'pcs', 'crate', 'box', 'pack'],
        'grains-rice' => ['kg', 'sack', 'pack'],
        'root-crops' => ['kg', 'pcs', 'sack', 'bundle'],
        'herbs-spices' => ['kg', 'g', 'bundle', 'pack', 'pcs'],
    ];

    public function getSellingUnitAttribute(): string
    {
        return $this->unit ?? 'kg';
    }

    public function getPriceUnitLabelAttribute(): string
    {
        $unit = strtolower(trim($this->unit ?? 'kg'));

        return match ($unit) {
            'pcs', 'pc', 'piece', 'pieces' => 'pc',
            'bundle', 'bundles' => 'bundle',
            'sack', 'sacks' => 'sack',
            'crate', 'crates' => 'crate',
            'box', 'boxes' => 'box',
            'pack', 'packs' => 'pack',
            'g', 'gram', 'grams' => 'g',
            default => 'kg',
        };
    }

    public function getStockUnitLabelAttribute(): string
    {
        $qty = (int) ($this->quantity ?? 0);
        $unit = strtolower(trim($this->unit ?? 'kg'));

        return match ($unit) {
            'pcs', 'pc', 'piece', 'pieces' => $qty === 1 ? 'pc' : 'pcs',
            'bundle', 'bundles' => $qty === 1 ? 'bundle' : 'bundles',
            'sack', 'sacks' => $qty === 1 ? 'sack' : 'sacks',
            'crate', 'crates' => $qty === 1 ? 'crate' : 'crates',
            'box', 'boxes' => $qty === 1 ? 'box' : 'boxes',
            'pack', 'packs' => $qty === 1 ? 'pack' : 'packs',
            'g', 'gram', 'grams' => 'g',
            default => 'kg',
        };
    }

    public function getStockWithUnitAttribute(): string
    {
        return "{$this->quantity} {$this->stock_unit_label}";
    }

    public function getHasWeightPerUnitAttribute(): bool
    {
        $unit = strtolower(trim($this->unit ?? 'kg'));
        if (in_array($unit, ['kg', 'g'], true)) {
            return false;
        }

        return ! is_null($this->kilograms) && (float) $this->kilograms > 0;
    }

    public function getFormattedWeightPerUnitAttribute(): ?string
    {
        if (! $this->has_weight_per_unit) {
            return null;
        }

        $val = (float) $this->kilograms;

        return number_format($val, 2).' kg';
    }

    public function getWeightPerUnitAttribute(): ?float
    {
        return ! is_null($this->kilograms) ? (float) $this->kilograms : null;
    }

    public function getEffectiveKilogramsAttribute(): float
    {
        $unit = strtolower(trim($this->unit ?? 'kg'));
        if ($unit === 'kg') {
            return 1.0;
        }
        if ($unit === 'g') {
            return 0.001;
        }
        if (! is_null($this->kilograms) && (float) $this->kilograms > 0) {
            return (float) $this->kilograms;
        }

        return 0.0;
    }

    public function getFormattedPricePerUnitAttribute(): string
    {
        return '₱'.number_format((float) $this->price, 2).' / '.$this->price_unit_label;
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

    public function getImagePathAttribute(?string $value): string
    {
        if (empty($value)) {
            return 'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=600&auto=format&fit=crop&q=80';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return asset(ltrim($value, '/'));
    }
}
