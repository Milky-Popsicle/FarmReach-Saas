<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recipe
 */
class RecipeResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'prep_time' => $this->prep_time,
            'image_path' => $this->image_path,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'ingredients' => $this->whenLoaded('ingredients', fn (): array => $this->ingredients
                ->map(fn ($ingredient): array => [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'quantity_description' => $ingredient->quantity_description,
                    'category' => $ingredient->category === null ? null : [
                        'id' => $ingredient->category->id,
                        'name' => $ingredient->category->name,
                        'slug' => $ingredient->category->slug,
                    ],
                ])
                ->all()),
        ];
    }
}
