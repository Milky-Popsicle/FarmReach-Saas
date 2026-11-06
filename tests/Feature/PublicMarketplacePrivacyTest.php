<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class PublicMarketplacePrivacyTest extends TestCase
{
    public function test_marketplace_listing_hides_farmer_addresses_and_phone_numbers(): void
    {
        $farmer = User::factory()->farmer()->create([
            'name' => 'Privacy Farmer',
            'address' => 'Private Marketplace Farm Road',
            'phone_number' => '09171112222',
        ]);
        $product = $this->createProduct($farmer, 'Visible Marketplace Squash');

        $response = $this->get(route('marketplace'));

        $response->assertOk()
            ->assertSee($product->name)
            ->assertSee($farmer->name)
            ->assertDontSee('Private Marketplace Farm Road')
            ->assertDontSee('09171112222');
    }

    public function test_comparison_page_hides_farmer_addresses_and_phone_numbers(): void
    {
        $farmer = User::factory()->farmer()->create([
            'name' => 'Comparison Farmer',
            'address' => 'Private Comparison Farm Road',
            'phone_number' => '09173334444',
        ]);
        $category = Category::factory()->create();
        $product = $this->createProduct($farmer, 'Visible Comparison Squash', [
            'category_id' => $category->id,
        ]);

        $response = $this->get(route('marketplace.compare', ['category_id' => $category->id]));

        $response->assertOk()
            ->assertSee($product->name)
            ->assertSee($farmer->name)
            ->assertDontSee('Private Comparison Farm Road')
            ->assertDontSee('09173334444');
    }

    public function test_marketplace_product_detail_hides_farmer_addresses_and_phone_numbers(): void
    {
        $farmer = User::factory()->farmer()->create([
            'name' => 'Detail Farmer',
            'address' => 'Private Detail Farm Road',
            'phone_number' => '09175556666',
        ]);
        $product = $this->createProduct($farmer, 'Visible Detail Squash');

        $response = $this->get(route('marketplace.show', $product));

        $response->assertOk()
            ->assertSee($product->name)
            ->assertSee($farmer->name)
            ->assertDontSee('Private Detail Farm Road')
            ->assertDontSee('09175556666');
    }

    public function test_marketplace_search_does_not_reveal_products_by_farmer_address(): void
    {
        $farmer = User::factory()->farmer()->create([
            'address' => 'Secret Orchard Lane 456',
        ]);
        $product = $this->createProduct($farmer, 'Unrelated Search Squash');

        $response = $this->get(route('marketplace', ['search' => 'Secret Orchard Lane']));

        $response->assertOk();

        $searchResults = $response->viewData('products');
        $this->assertTrue($searchResults->isEmpty());
        $this->assertNotContains($product->id, $searchResults->pluck('id')->all());
    }

    public function test_marketplace_search_still_matches_farmer_names(): void
    {
        $farmer = User::factory()->farmer()->create([
            'name' => 'Searchable Farmer Name',
        ]);
        $product = $this->createProduct($farmer, 'Farmer Name Match Squash');

        $response = $this->get(route('marketplace', ['search' => 'Searchable Farmer Name']));

        $response->assertOk()
            ->assertSee($product->name);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProduct(User $farmer, string $name, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'user_id' => $farmer->id,
            'category_id' => Category::factory(),
            'name' => $name,
            'price' => 50.00,
            'quantity' => 10,
            'unit' => 'kg',
            'is_active' => true,
        ], $attributes));
    }
}
