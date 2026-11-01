<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductCategorySeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NewProductCategoriesTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private const NEW_CATEGORY_SLUGS = [
        'eggs-dairy',
        'snacks-processed-farm-products',
        'plants-seedlings',
    ];

    /**
     * @var array<int, string>
     */
    private const ALL_CATEGORY_SLUGS = [
        'vegetables',
        'fruits',
        'grains-rice',
        'root-crops',
        'herbs-spices',
        'eggs-dairy',
        'snacks-processed-farm-products',
        'plants-seedlings',
    ];

    public function test_product_categories_are_seeded_with_expected_names_and_slugs(): void
    {
        $this->seed(ProductCategorySeeder::class);

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseHas('categories', [
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'icon' => 'bi-flower1',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Fruits',
            'slug' => 'fruits',
            'icon' => 'bi-apple',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Grains & Rice',
            'slug' => 'grains-rice',
            'icon' => 'bi-layers',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Root Crops',
            'slug' => 'root-crops',
            'icon' => 'bi-box-seam',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Herbs & Spices',
            'slug' => 'herbs-spices',
            'icon' => 'bi-fire',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Eggs & Dairy',
            'slug' => 'eggs-dairy',
            'icon' => 'bi-egg-fried',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Snacks & Processed Farm Products',
            'slug' => 'snacks-processed-farm-products',
            'icon' => 'bi-cookie',
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Plants & Seedlings',
            'slug' => 'plants-seedlings',
            'icon' => 'bi-tree',
        ]);
    }

    public function test_product_category_seeder_initializes_all_categories_for_existing_production_admin(): void
    {
        User::factory()->admin()->create([
            'email' => 'owner@farmreach.ph',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'admin@farmreach.ph']);
        $this->assertDatabaseCount('categories', 0);

        $this->seed(ProductCategorySeeder::class);

        $this->assertDatabaseCount('categories', 8);

        foreach (self::ALL_CATEGORY_SLUGS as $slug) {
            $this->assertDatabaseHas('categories', ['slug' => $slug]);
        }
    }

    public function test_product_category_seeder_does_not_create_duplicates(): void
    {
        $this->seed(ProductCategorySeeder::class);
        $this->seed(ProductCategorySeeder::class);

        $this->assertDatabaseCount('categories', 8);

        foreach (self::ALL_CATEGORY_SLUGS as $slug) {
            $this->assertSame(1, Category::where('slug', $slug)->count());
        }
    }

    public function test_product_category_seeder_skips_existing_category_with_same_name(): void
    {
        Category::create([
            'name' => 'Eggs & Dairy',
            'slug' => 'custom-eggs-dairy',
            'icon' => 'bi-egg',
            'description' => 'An existing category with the same display name.',
        ]);

        $this->seed(ProductCategorySeeder::class);

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseHas('categories', [
            'name' => 'Eggs & Dairy',
            'slug' => 'custom-eggs-dairy',
        ]);
        $this->assertDatabaseMissing('categories', [
            'slug' => 'eggs-dairy',
        ]);
        $this->assertSame(1, Category::where('name', 'Eggs & Dairy')->count());
    }

    public function test_product_category_seeder_skips_existing_category_with_same_slug(): void
    {
        Category::create([
            'name' => 'Existing Eggs Category',
            'slug' => 'eggs-dairy',
            'icon' => 'bi-egg',
            'description' => 'An existing category with the same slug.',
        ]);

        $this->seed(ProductCategorySeeder::class);

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseHas('categories', [
            'name' => 'Existing Eggs Category',
            'slug' => 'eggs-dairy',
        ]);
        $this->assertDatabaseMissing('categories', [
            'name' => 'Eggs & Dairy',
        ]);
        $this->assertSame(1, Category::where('slug', 'eggs-dairy')->count());
    }

    public function test_database_seeder_adds_new_categories_when_demo_data_already_exists(): void
    {
        $this->seed();

        Category::whereIn('slug', self::NEW_CATEGORY_SLUGS)->delete();

        $this->assertDatabaseCount('categories', 5);
        $existingProductCount = Product::count();

        $this->seed();

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('products', $existingProductCount);
        $this->assertDatabaseHas('categories', [
            'name' => 'Vegetables',
            'slug' => 'vegetables',
        ]);

        foreach (self::NEW_CATEGORY_SLUGS as $slug) {
            $this->assertDatabaseHas('categories', ['slug' => $slug]);
        }
    }

    public function test_database_seeder_resolves_base_category_with_custom_slug(): void
    {
        $this->seed(ProductCategorySeeder::class);

        $vegetables = Category::where('slug', 'vegetables')->firstOrFail();
        $vegetables->update(['slug' => 'veg']);

        $this->assertDatabaseMissing('categories', ['slug' => 'vegetables']);

        // The admin account does not exist yet, so DatabaseSeeder runs the demo
        // data path that resolves the five base categories.
        $this->seed();

        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseHas('categories', [
            'id' => $vegetables->id,
            'name' => 'Vegetables',
            'slug' => 'veg',
        ]);
        $this->assertDatabaseMissing('categories', ['slug' => 'vegetables']);
        $this->assertSame(1, Category::where('name', 'Vegetables')->count());
        $this->assertTrue(Product::where('category_id', $vegetables->id)->exists());
    }

    public function test_farmer_product_page_lists_new_categories(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();

        $response = $this->actingAs($farmer)->get(route('farmer.products'));

        $response->assertSee('Eggs & Dairy')
            ->assertSee('Snacks & Processed Farm Products')
            ->assertSee('Plants & Seedlings');
    }

    public function test_farmer_can_create_and_update_products_in_new_categories(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $eggsCategory = Category::where('slug', 'eggs-dairy')->firstOrFail();
        $plantsCategory = Category::where('slug', 'plants-seedlings')->firstOrFail();

        $this->actingAs($farmer)->post(route('farmer.products.store'), [
            'name' => 'Farm Fresh Eggs Create Test',
            'category_id' => $eggsCategory->id,
            'price' => 250.00,
            'quantity' => 30,
            'unit' => 'pcs',
            'description' => 'Fresh eggs listed under the new eggs and dairy category.',
        ])->assertSessionHas('success');

        $product = Product::where('name', 'Farm Fresh Eggs Create Test')->firstOrFail();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $eggsCategory->id,
        ]);

        $this->actingAs($farmer)->put(route('farmer.products.update', $product), [
            'name' => 'Farm Fresh Eggs Create Test',
            'category_id' => $plantsCategory->id,
            'price' => 250.00,
            'quantity' => 30,
            'unit' => 'pcs',
            'description' => 'Reassigned to the new plants and seedlings category.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $plantsCategory->id,
        ]);
    }

    public static function newCategoryFilters(): array
    {
        return [
            'eggs and dairy' => ['eggs-dairy', 'Farm Fresh Eggs Filter Test'],
            'snacks and processed farm products' => ['snacks-processed-farm-products', 'Banana Chips Filter Test'],
            'plants and seedlings' => ['plants-seedlings', 'Tomato Seedlings Filter Test'],
        ];
    }

    #[DataProvider('newCategoryFilters')]
    public function test_marketplace_category_filter_returns_only_products_in_new_category(
        string $categorySlug,
        string $productName
    ): void {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $category = Category::where('slug', $categorySlug)->firstOrFail();
        $product = Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => $category->id,
            'name' => $productName,
            'price' => 100.00,
            'quantity' => 20,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $response = $this->get(route('marketplace', ['category' => $category->id]));

        $response->assertViewHas('products', function ($products) use ($category, $product): bool {
            $items = collect($products->items());

            return $items->contains(fn (Product $item): bool => $item->is($product))
                && $items->every(fn (Product $item): bool => $item->category_id === $category->id);
        });
        $response->assertSee($product->name)
            ->assertSee($category->name);
    }

    public function test_product_detail_page_displays_new_category_name(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $category = Category::where('slug', 'eggs-dairy')->firstOrFail();
        $product = Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => $category->id,
            'name' => 'Farm Fresh Eggs Detail Test',
            'price' => 250.00,
            'quantity' => 30,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $response = $this->get(route('marketplace.show', $product));

        $response->assertSee($product->name)
            ->assertSee($category->name);
    }

    public function test_api_products_can_filter_by_new_category(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $category = Category::where('slug', 'plants-seedlings')->firstOrFail();
        $product = Product::factory()->create([
            'user_id' => $farmer->id,
            'category_id' => $category->id,
            'name' => 'Tomato Seedlings API Test',
            'price' => 75.00,
            'quantity' => 40,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products?category_id='.$category->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $product->name)
            ->assertJsonPath('data.0.category.slug', $category->slug);
    }

    public function test_admin_recipe_form_lists_new_categories(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.recipes'));

        $response->assertSee('Eggs & Dairy')
            ->assertSee('Snacks & Processed Farm Products')
            ->assertSee('Plants & Seedlings');
    }
}
