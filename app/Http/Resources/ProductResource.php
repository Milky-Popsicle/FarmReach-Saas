<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'quantity' => (int) $this->quantity,
            'kilograms' => $this->kilograms !== null ? (float) $this->kilograms : null,
            'unit' => $this->unit,
            'image_path' => $this->image_path,
            'is_active' => (bool) $this->is_active,
            'stock_status' => $this->stock_status,
            'stock_with_unit' => $this->stock_with_unit,
            'price_unit_label' => $this->price_unit_label,
            'stock_unit_label' => $this->stock_unit_label,
            'has_weight_per_unit' => $this->has_weight_per_unit,
            'formatted_weight_per_unit' => $this->formatted_weight_per_unit,
            'formatted_price_per_unit' => $this->formatted_price_per_unit,
            'selling_unit' => $this->selling_unit,
            'farmer' => $this->whenLoaded('farmer', fn (): ?array => $this->farmer === null ? null : [
                'id' => $this->farmer->id,
                'name' => $this->farmer->name,
                'bio' => $this->farmer->bio,
            ]),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
        ];
    }
}
