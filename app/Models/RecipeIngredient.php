<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'name',
        'quantity_description',
        'category_id',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Match this ingredient with available products from local farmers.
     */
    public function getAvailableProducts()
    {
        $query = Product::with('farmer')
            ->where('is_active', true)
            ->where('quantity', '>', 0);

        $name = trim($this->name);
        // Match either by direct name substring or category
        $query->where(function ($q) use ($name) {
            $q->where('name', 'LIKE', '%' . $name . '%');
            if ($this->category_id) {
                $q->orWhere('category_id', $this->category_id);
            }
        });

        return $query->get();
    }
}
