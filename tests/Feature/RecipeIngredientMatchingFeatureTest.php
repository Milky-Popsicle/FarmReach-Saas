<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Tests\TestCase;

class RecipeIngredientMatchingFeatureTest extends TestCase
{
    public function test_recipe_match_returns_only_products_for_the_selected_recipes_ingredients(): void
    {
        $fixture = $this->createRecipeMatchingFixture();

        $response = $this->getJson('/api/recipes/'.$fixture['adobo']->id.'/match');

        $response->assertOk()
            ->assertJsonCount(2, 'ingredients');

        $matches = collect($response->json('ingredients'))
            ->mapWithKeys(fn (array $ingredient): array => [
                $ingredient['ingredient_name'] => collect($ingredient['matched_products'])->pluck('name')->all(),
            ]);

        $this->assertSame([
            'Potato' => ['Fresh Potato'],
            'Onion' => ['Fresh Onion'],
        ], $matches->all());

        $response->assertJsonMissing(['name' => 'Fresh Sayote'])
            ->assertJsonMissing(['name' => 'Fresh Carrot']);
    }

    public function test_recipe_match_can_be_scoped_to_one_ingredient_that_belongs_to_the_recipe(): void
    {
        $fixture = $this->createRecipeMatchingFixture();

        $response = $this->getJson('/api/recipes/'.$fixture['adobo']->id.'/match?ingredient_id='.$fixture['potatoIngredient']->id);

        $response->assertOk()
            ->assertJsonCount(1, 'ingredients')
            ->assertJsonPath('ingredients.0.ingredient_name', 'Potato')
            ->assertJsonPath('ingredients.0.matched_products.0.name', 'Fresh Potato')
            ->assertJsonPath('ingredients.0.matched_products.0.price', 25)
            ->assertJsonPath('ingredients.0.matched_products.0.quantity_in_stock', 10);

        $this->assertSame(
            ['Fresh Potato'],
            collect($response->json('ingredients.0.matched_products'))->pluck('name')->all()
        );
    }

    public function test_recipe_match_rejects_an_ingredient_from_another_recipe(): void
    {
        $fixture = $this->createRecipeMatchingFixture();

        $response = $this->getJson('/api/recipes/'.$fixture['adobo']->id.'/match?ingredient_id='.$fixture['sayoteIngredient']->id);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'The selected ingredient does not belong to this recipe.');
    }

    public function test_recipe_match_switches_to_the_newly_selected_recipe(): void
    {
        $fixture = $this->createRecipeMatchingFixture();

        $response = $this->getJson('/api/recipes/'.$fixture['sayoteRecipe']->id.'/match');

        $response->assertOk()
            ->assertJsonCount(2, 'ingredients')
            ->assertJsonMissing(['name' => 'Fresh Potato']);

        $matches = collect($response->json('ingredients'))
            ->mapWithKeys(fn (array $ingredient): array => [
                $ingredient['ingredient_name'] => collect($ingredient['matched_products'])->pluck('name')->all(),
            ]);

        $this->assertSame([
            'Sayote' => ['Fresh Sayote'],
            'Carrot' => ['Fresh Carrot'],
        ], $matches->all());
    }

    public function test_recipe_page_does_not_show_other_recipes_products_or_farmer_addresses(): void
    {
        $fixture = $this->createRecipeMatchingFixture();

        $response = $this->get(route('recipes.show', $fixture['adobo']));

        $response->assertOk()
            ->assertSee('Fresh Potato')
            ->assertSee('Fresh Onion')
            ->assertDontSee('Fresh Sayote')
            ->assertDontSee('Fresh Carrot')
            ->assertDontSee('Private Recipe Farm Address');
    }

    public function test_recipe_with_no_ingredients_shows_an_empty_state(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Empty Recipe']);

        $this->get(route('recipes.show', $recipe))
            ->assertOk()
            ->assertSee('does not have any ingredients to match yet');

        $this->getJson('/api/recipes/'.$recipe->id.'/match')
            ->assertOk()
            ->assertJsonCount(0, 'ingredients');
    }

    public function test_matched_ingredient_products_can_still_be_added_to_the_cart(): void
    {
        $fixture = $this->createRecipeMatchingFixture();

        $response = $this->post(route('recipes.add-batch'), [
            'products' => [
                $fixture['potatoProduct']->id => [
                    'id' => $fixture['potatoProduct']->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success');

        $cart = session('cart');

        $this->assertIsArray($cart);
        $this->assertArrayHasKey($fixture['potatoProduct']->id, $cart);
        $this->assertSame(2, $cart[$fixture['potatoProduct']->id]['quantity']);
        $this->assertSame(25.0, (float) $cart[$fixture['potatoProduct']->id]['price']);
    }

    /**
     * @return array{
     *     adobo: Recipe,
     *     sayoteRecipe: Recipe,
     *     potatoIngredient: RecipeIngredient,
     *     onionIngredient: RecipeIngredient,
     *     sayoteIngredient: RecipeIngredient,
     *     carrotIngredient: RecipeIngredient,
     *     potatoProduct: Product,
     *     onionProduct: Product,
     *     sayoteProduct: Product,
     *     carrotProduct: Product
     * }
     */
    private function createRecipeMatchingFixture(): array
    {
        $category = Category::factory()->create(['name' => 'Vegetables']);
        $farmer = User::factory()->farmer()->create([
            'address' => 'Private Recipe Farm Address',
        ]);

        $adobo = Recipe::factory()->create(['title' => 'Adobo']);
        $potatoIngredient = RecipeIngredient::factory()->create([
            'recipe_id' => $adobo->id,
            'name' => 'Potato',
            'category_id' => $category->id,
        ]);
        $onionIngredient = RecipeIngredient::factory()->create([
            'recipe_id' => $adobo->id,
            'name' => 'Onion',
            'category_id' => $category->id,
        ]);

        $sayoteRecipe = Recipe::factory()->create(['title' => 'Sayote Stir Fry']);
        $sayoteIngredient = RecipeIngredient::factory()->create([
            'recipe_id' => $sayoteRecipe->id,
            'name' => 'Sayote',
            'category_id' => $category->id,
        ]);
        $carrotIngredient = RecipeIngredient::factory()->create([
            'recipe_id' => $sayoteRecipe->id,
            'name' => 'Carrot',
            'category_id' => $category->id,
        ]);

        $potatoProduct = Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => $category->id,
            'name' => 'Fresh Potato',
            'price' => 25.00,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $onionProduct = Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => $category->id,
            'name' => 'Fresh Onion',
            'price' => 15.00,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $sayoteProduct = Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => $category->id,
            'name' => 'Fresh Sayote',
            'price' => 20.00,
            'quantity' => 10,
            'is_active' => true,
        ]);
        $carrotProduct = Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => $category->id,
            'name' => 'Fresh Carrot',
            'price' => 18.00,
            'quantity' => 10,
            'is_active' => true,
        ]);

        return compact(
            'adobo',
            'sayoteRecipe',
            'potatoIngredient',
            'onionIngredient',
            'sayoteIngredient',
            'carrotIngredient',
            'potatoProduct',
            'onionProduct',
            'sayoteProduct',
            'carrotProduct',
        );
    }
}
