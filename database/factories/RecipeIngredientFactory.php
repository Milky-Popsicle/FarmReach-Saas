<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeIngredient>
 */
class RecipeIngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'name' => fake()->words(2, true),
            'quantity_description' => fake()->words(3, true),
            'category_id' => Category::factory(),
        ];
    }
}
