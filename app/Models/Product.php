<?php

namespace App\Models;

use App\Notifications\LowStockNotification;
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
            'low_stock_notified_at' => 'datetime',
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
        'eggs-dairy' => ['pcs', 'pack', 'crate', 'box'],
        'snacks-processed-farm-products' => ['pack', 'pcs', 'box', 'bundle'],
        'plants-seedlings' => ['pcs', 'bundle', 'pack', 'crate', 'box'],
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

    public static function lowStockThreshold(): int
    {
        return max(0, (int) config('farmreach.low_stock_threshold', 10));
    }

    public function isLowOnStock(): bool
    {
        return (int) $this->quantity <= static::lowStockThreshold();
    }

    /**
     * Notify the product owner once per low-stock cycle.
     */
    public function syncLowStockNotification(): void
    {
        $this->refresh();

        if (! $this->isLowOnStock()) {
            $this->clearLowStockNotification();

            return;
        }

        if ($this->low_stock_notified_at !== null) {
            return;
        }

        $farmer = $this->farmer;

        if (! $farmer) {
            return;
        }

        $notifiedAt = now();

        $claimed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('low_stock_notified_at')
            ->toBase()
            ->update(['low_stock_notified_at' => $notifiedAt]);

        if ($claimed < 1) {
            $this->refresh();

            return;
        }

        $this->low_stock_notified_at = $notifiedAt;

        $farmer->notify(new LowStockNotification(
            productName: $this->name,
            remainingQuantity: max(0, (int) $this->quantity),
            unitLabel: $this->stock_unit_label,
            threshold: static::lowStockThreshold(),
        ));
    }

    /**
     * Re-enable future low-stock alerts after the product is replenished.
     */
    public function resetLowStockNotificationIfReplenished(): void
    {
        $this->refresh();

        if ($this->quantity > static::lowStockThreshold()) {
            $this->clearLowStockNotification();
        }
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->quantity <= 0) {
            return 'Out of Stock';
        }
        if ($this->isLowOnStock()) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    private function clearLowStockNotification(): void
    {
        if ($this->low_stock_notified_at === null) {
            return;
        }

        static::query()
            ->whereKey($this->getKey())
            ->whereNotNull('low_stock_notified_at')
            ->toBase()
            ->update(['low_stock_notified_at' => null]);

        $this->low_stock_notified_at = null;
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

    /**
     * Build the session cart payload for this product.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     price: float,
     *     kilograms: float,
     *     weight_per_unit: float|null,
     *     has_weight: bool,
     *     unit: string|null,
     *     selling_unit: string|null,
     *     stock_unit_label: string,
     *     price_unit_label: string,
     *     image_path: string,
     *     farmer_name: string|null,
     *     farmer_id: int,
     *     farmer_address: string|null,
     *     quantity: int,
     *     order_type: string
     * }
     */
    public function cartItemPayload(int $quantity, ?string $orderType = null): array
    {
        if (! in_array($orderType, ['standard', 'bulk'], true)) {
            $orderType = null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'kilograms' => (float) $this->effective_kilograms,
            'weight_per_unit' => $this->kilograms ? (float) $this->kilograms : null,
            'has_weight' => $this->has_weight_per_unit,
            'unit' => $this->unit,
            'selling_unit' => $this->unit,
            'stock_unit_label' => $this->stock_unit_label,
            'price_unit_label' => $this->price_unit_label,
            'image_path' => $this->image_path,
            'farmer_name' => $this->farmer?->name,
            'farmer_id' => $this->user_id,
            'farmer_address' => $this->farmer?->address,
            'quantity' => $quantity,
            'order_type' => $orderType ?? ($quantity >= 20 ? 'bulk' : 'standard'),
        ];
    }
}
