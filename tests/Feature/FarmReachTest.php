<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Tests\TestCase;

class FarmReachTest extends TestCase
{
    public function test_marketplace_page_loads_successfully(): void
    {
        $this->seed();

        $response = $this->get('/marketplace');

        $response->assertStatus(200);
        $response->assertSee('FarmReach');
        $response->assertSee('Direct From Surigao Farmers');
    }

    public function test_root_redirects_to_marketplace(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/marketplace');
    }

    public function test_admin_can_login_using_email(): void
    {
        $this->seed();

        $response = $this->post('/login', [
            'login_id' => 'admin@farmreach.ph',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_farmer_can_login_using_phone_number(): void
    {
        $this->seed();

        $response = $this->post('/login', [
            'login_id' => '09876543210',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('farmer.marketplace'));
        $this->assertAuthenticated();
    }

    public function test_buyer_can_login_and_view_my_orders(): void
    {
        $this->seed();
        $buyer = User::where('email', 'shery@buyer.ph')->firstOrFail();

        $this->actingAs($buyer);

        $response = $this->get('/buyer/orders');
        $response->assertStatus(200);
        $response->assertSee('My Orders');
        $response->assertSee('Card View');
        $response->assertSee('Table View');
    }

    public function test_public_registration_rejects_administrator_role_even_when_one_exists(): void
    {
        $this->seed();

        $response = $this->post('/register', [
            'name' => 'Second Admin Attempt',
            'email' => 'secondadmin@farmreach.ph',
            'phone_number' => '09998887766',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors([
            'role' => 'Administrator accounts cannot be created through public registration.',
        ]);
    }

    public function test_public_registration_cannot_create_an_administrator_when_none_exists(): void
    {
        $this->assertDatabaseCount('users', 0);

        $response = $this->post('/register', [
            'name' => 'Unauthorized Admin Attempt',
            'email' => 'firstadmin@farmreach.ph',
            'phone_number' => '09991112222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors(['role']);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'firstadmin@farmreach.ph']);
        $this->assertDatabaseMissing('users', ['role' => 'admin']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_page_does_not_offer_administrator_registration(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertDontSee('value="admin"', false);
        $response->assertDontSee('System Owner');
        $response->assertSee('value="buyer"', false);
        $response->assertSee('value="farmer"', false);
    }

    public function test_public_registration_still_creates_buyer_and_farmer_accounts(): void
    {
        $this->post('/register', [
            'name' => 'New Buyer',
            'email' => 'new-buyer@farmreach.ph',
            'phone_number' => '09990001111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'buyer',
            'address' => 'Surigao City',
        ])->assertRedirect(route('marketplace'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'new-buyer@farmreach.ph',
            'role' => 'buyer',
        ]);

        $this->post('/logout');

        $this->post('/register', [
            'name' => 'New Farmer',
            'email' => 'new-farmer@farmreach.ph',
            'phone_number' => '09990002222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'farmer',
            'address' => 'Surigao del Norte',
        ])->assertRedirect(route('farmer.marketplace'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'new-farmer@farmreach.ph',
            'role' => 'farmer',
        ]);
    }

    public function test_recipe_to_product_matching_view(): void
    {
        $this->seed();
        $recipe = Recipe::firstOrFail();

        $response = $this->get(route('recipes.show', $recipe));

        $response->assertStatus(200);
        $response->assertSee('Ingredient-to-Marketplace Matcher');
    }

    public function test_buyer_can_add_to_cart_and_place_order(): void
    {
        $this->seed();
        $buyer = User::where('email', 'shery@buyer.ph')->firstOrFail();
        $product = Product::where('quantity', '>', 5)->where('is_active', true)->firstOrFail();

        $this->actingAs($buyer);

        $cartResponse = $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $cartResponse->assertSessionHas('cart');

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

    public function test_farmer_can_confirm_order(): void
    {
        $this->seed();
        $order = Order::where('status', 'pending')->firstOrFail();
        $farmer = $order->farmer;

        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'confirmed',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_public_rest_api_endpoints_return_catalog_data(): void
    {
        $this->seed();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->getJson('/api/recipes')
            ->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_sensitive_rest_api_endpoints_require_authentication(): void
    {
        $this->seed();

        $this->getJson('/api/sales-data')->assertUnauthorized();
        $this->getJson('/api/farmer-locations')->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/recent-activities')->assertUnauthorized();
    }

    public function test_marketplace_product_detail_page_loads_successfully(): void
    {
        $this->seed();
        $product = Product::where('is_active', true)->firstOrFail();

        $response = $this->get(route('marketplace.show', $product));

        $response->assertStatus(200);
        $response->assertSee($product->name);
    }

    public function test_admin_can_update_announcement(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();
        $announcement = Announcement::firstOrFail();

        $response = $this->actingAs($admin)->put(route('admin.announcements.update', $announcement), [
            'title' => 'Updated Test Announcement Title',
            'location' => 'Surigao City Hall',
            'event_date' => 'Next Month',
            'description' => 'Updated announcement description text.',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('Updated Test Announcement Title', $announcement->fresh()->title);
    }

    public function test_farmer_cannot_purchase_own_crop_listing(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $ownProduct = Product::where('user_id', $farmer->id)->firstOrFail();

        $response = $this->actingAs($farmer)->post('/cart/add', [
            'product_id' => $ownProduct->id,
            'quantity' => 1,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_farmer_can_purchase_produce_from_another_farmer(): void
    {
        $this->seed();
        $farmer = User::where('role', 'farmer')->firstOrFail();
        $otherFarmerProduct = Product::where('user_id', '!=', $farmer->id)
            ->where('quantity', '>', 5)
            ->firstOrFail();

        $response = $this->actingAs($farmer)->post('/cart/add', [
            'product_id' => $otherFarmerProduct->id,
            'quantity' => 1,
        ]);

        $response->assertSessionHas('cart');
    }
}
