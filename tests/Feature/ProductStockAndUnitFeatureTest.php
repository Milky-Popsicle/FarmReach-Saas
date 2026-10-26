<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class ProductStockAndUnitFeatureTest extends TestCase
{
    public function test_farmer_can_create_product_with_piece_unit_and_optional_weight(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $category = Category::where('slug', 'fruits')->firstOrFail();

        $response = $this->actingAs($farmer)->post(route('farmer.products.store'), [
            'name' => 'Fuji Apple',
            'category_id' => $category->id,
            'price' => 20.00,
            'quantity' => 25,
            'unit' => 'pcs',
            'kilograms' => 0.20,
            'description' => 'Crisp fresh apples sold by piece.',
        ]);

        $response->assertSessionHas('success');

        $product = Product::where('name', 'Fuji Apple')->firstOrFail();
        $this->assertSame(25, $product->quantity);
        $this->assertSame('pcs', $product->unit);
        $this->assertEquals(0.20, (float) $product->kilograms);
        $this->assertSame('25 pcs', $product->stock_with_unit);
        $this->assertSame('pc', $product->price_unit_label);
        $this->assertSame('₱20.00 / pc', $product->formatted_price_per_unit);
        $this->assertTrue($product->has_weight_per_unit);
        $this->assertSame('0.20 kg', $product->formatted_weight_per_unit);
    }

    public function test_farmer_can_create_weight_based_product_without_separate_weight_per_unit(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $category = Category::where('slug', 'vegetables')->firstOrFail();

        $response = $this->actingAs($farmer)->post(route('farmer.products.store'), [
            'name' => 'Organic Fresh Tomatoes',
            'category_id' => $category->id,
            'price' => 100.00,
            'quantity' => 30,
            'unit' => 'kg',
            'description' => 'Fresh native tomatoes sold by kilogram.',
        ]);

        $response->assertSessionHas('success');

        $product = Product::where('name', 'Organic Fresh Tomatoes')->firstOrFail();
        $this->assertSame(30, $product->quantity);
        $this->assertSame('kg', $product->unit);
        $this->assertNull($product->kilograms);
        $this->assertSame('30 kg', $product->stock_with_unit);
        $this->assertSame('kg', $product->price_unit_label);
        $this->assertSame('₱100.00 / kg', $product->formatted_price_per_unit);
        $this->assertFalse($product->has_weight_per_unit);
        $this->assertNull($product->formatted_weight_per_unit);
    }

    public function test_farmer_can_create_bundle_and_sack_products(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $vegCat = Category::where('slug', 'vegetables')->firstOrFail();
        $grainCat = Category::where('slug', 'grains-rice')->firstOrFail();

        $this->actingAs($farmer)->post(route('farmer.products.store'), [
            'name' => 'Organic Pechay Test',
            'category_id' => $vegCat->id,
            'price' => 30.00,
            'quantity' => 20,
            'unit' => 'bundle',
        ]);

        $pechay = Product::where('name', 'Organic Pechay Test')->firstOrFail();
        $this->assertSame('20 bundles', $pechay->stock_with_unit);
        $this->assertSame('₱30.00 / bundle', $pechay->formatted_price_per_unit);
        $this->assertFalse($pechay->has_weight_per_unit);

        $this->actingAs($farmer)->post(route('farmer.products.store'), [
            'name' => 'Special Rice Test',
            'category_id' => $grainCat->id,
            'price' => 2500.00,
            'quantity' => 10,
            'unit' => 'sack',
            'kilograms' => 50.00,
        ]);

        $rice = Product::where('name', 'Special Rice Test')->firstOrFail();
        $this->assertSame('10 sacks', $rice->stock_with_unit);
        $this->assertSame('₱2,500.00 / sack', $rice->formatted_price_per_unit);
        $this->assertTrue($rice->has_weight_per_unit);
        $this->assertSame('50.00 kg', $rice->formatted_weight_per_unit);
    }

    public function test_farmer_can_update_product_stock_and_selling_unit(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $product = Product::where('user_id', $farmer->id)->firstOrFail();

        $response = $this->actingAs($farmer)->put(route('farmer.products.update', $product), [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price' => 450.00,
            'quantity' => 15,
            'unit' => 'crate',
            'kilograms' => 12.50,
            'description' => 'Updated crate produce.',
        ]);

        $response->assertSessionHas('success');

        $fresh = $product->fresh();
        $this->assertSame(15, $fresh->quantity);
        $this->assertSame('crate', $fresh->unit);
        $this->assertEquals(12.50, (float) $fresh->kilograms);
        $this->assertSame('15 crates', $fresh->stock_with_unit);
        $this->assertSame('₱450.00 / crate', $fresh->formatted_price_per_unit);
    }

    public function test_product_views_display_stock_and_proper_labels(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $cat = Category::firstOrFail();

        $cabbage = Product::create([
            'user_id' => $farmer->id,
            'category_id' => $cat->id,
            'name' => 'Highland Cabbage Head',
            'price' => 60.00,
            'quantity' => 30,
            'unit' => 'pcs',
            'kilograms' => 1.50,
            'is_active' => true,
        ]);

        $grapes = Product::create([
            'user_id' => $farmer->id,
            'category_id' => $cat->id,
            'name' => 'Sweet Crimson Grapes',
            'price' => 201.00,
            'quantity' => 11,
            'unit' => 'kg',
            'kilograms' => null,
            'is_active' => true,
        ]);

        $farmerPage = $this->actingAs($farmer)->get(route('farmer.products'));
        $farmerPage->assertStatus(200);
        $farmerPage->assertSee('Stock:');
        $farmerPage->assertSee('30 pcs');
        $farmerPage->assertSee('₱60.00 / pc');
        $farmerPage->assertSee('Weight per Unit:');
        $farmerPage->assertSee('1.50 kg');

        $cabbagePage = $this->get(route('marketplace.show', $cabbage));
        $cabbagePage->assertStatus(200);
        $cabbagePage->assertSee('Stock: 30 pcs');
        $cabbagePage->assertSee('/ pc');
        $cabbagePage->assertSee('Weight per Unit:');
        $cabbagePage->assertSee('1.50 kg');

        $grapesPage = $this->get(route('marketplace.show', $grapes));
        $grapesPage->assertStatus(200);
        $grapesPage->assertSee('Stock: 11 kg');
        $grapesPage->assertSee('/ kg');
        $grapesPage->assertDontSee('Weight per Unit: <strong>');

        $comparePage = $this->get(route('marketplace.compare', ['category_id' => $cat->id]));
        $comparePage->assertStatus(200);
        $comparePage->assertSee('Selling Unit & Weight', false);
        $comparePage->assertSee('Stock:');
    }
}
