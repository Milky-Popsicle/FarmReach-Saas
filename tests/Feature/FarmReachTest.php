<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Tests\TestCase;

class FarmReachTest extends TestCase
{
    public function test_marketplace_page_loads_successfully()
    {
        $response = $this->get('/marketplace');
        $response->assertStatus(200);
        $response->assertSee('FarmReach');
        $response->assertSee('Direct From Surigao Farmers');
    }

    public function test_root_redirects_to_marketplace()
    {
        $response = $this->get('/');
        $response->assertRedirect('/marketplace');
    }

    public function test_admin_can_login_using_email()
    {
        $response = $this->post('/login', [
            'login_id' => 'admin@farmreach.ph',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_farmer_can_login_using_phone_number()
    {
        $response = $this->post('/login', [
            'login_id' => '09876543210',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('farmer.marketplace'));
        $this->assertAuthenticated();
    }

    public function test_buyer_can_login_and_view_my_orders()
    {
        $buyer = User::where('email', 'shery@buyer.ph')->first();
        $this->actingAs($buyer);

        $response = $this->get('/buyer/orders');
        $response->assertStatus(200);
        $response->assertSee('My Orders');
        $response->assertSee('Card View');
        $response->assertSee('Table View');
    }

    public function test_single_admin_constraint_blocks_second_admin_registration()
    {
        $response = $this->post('/register', [
            'name' => 'Second Admin Attempt',
            'email' => 'secondadmin@farmreach.ph',
            'phone_number' => '09998887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors(['role']);
    }

    public function test_recipe_to_product_matching_view()
    {
        $recipe = Recipe::first();
        $this->assertNotNull($recipe);

        $response = $this->get(route('recipes.show', $recipe));
        $response->assertStatus(200);
        $response->assertSee('Ingredient-to-Marketplace Matcher');
    }

    public function test_buyer_can_add_to_cart_and_place_order()
    {
        $buyer = User::where('email', 'shery@buyer.ph')->first();
        $this->actingAs($buyer);

        $product = Product::where('quantity', '>', 5)->first();

        // 1. Add to cart
        $cartResponse = $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $cartResponse->assertSessionHas('cart');

        // 2. Checkout & Place Order
        $checkoutResponse = $this->post('/checkout', [
            'delivery_method' => 'deliver',
            'delivery_address' => 'Test Delivery St., Surigao City',
            'payment_method' => 'cod',
            'notes' => 'Call on delivery',
        ]);

        $checkoutResponse->assertRedirect(route('buyer.orders'));
        $this->assertDatabaseHas('orders', [
            'buyer_id' => $buyer->id,
            'status' => 'pending',
            'delivery_method' => 'deliver',
        ]);
    }

    public function test_farmer_can_confirm_order()
    {
        $farmer = User::where('role', 'farmer')->first();
        $this->actingAs($farmer);

        $order = \App\Models\Order::where('farmer_id', $farmer->id)->where('status', 'pending')->first();
        if ($order) {
            $response = $this->post(route('farmer.orders.status', $order), [
                'status' => 'confirmed',
            ]);
            $response->assertSessionHas('success');
            $this->assertEquals('confirmed', $order->fresh()->status);
        }
    }

    public function test_rest_api_endpoints()
    {
        $prodResponse = $this->getJson('/api/products');
        $prodResponse->assertStatus(200);
        $prodResponse->assertJson(['status' => 'success']);

        $salesResponse = $this->getJson('/api/sales-data');
        $salesResponse->assertStatus(200);
        $salesResponse->assertJson(['status' => 'success']);

        $farmerResponse = $this->getJson('/api/farmer-locations');
        $farmerResponse->assertStatus(200);
        $farmerResponse->assertJson(['status' => 'success']);
    }
}
