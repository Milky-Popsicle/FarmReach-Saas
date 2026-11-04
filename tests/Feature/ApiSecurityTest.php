<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'orders' => ['/api/orders'],
            'bulk orders' => ['/api/bulk-orders'],
            'farmer locations' => ['/api/farmer-locations'],
            'sales data' => ['/api/sales-data'],
            'top products' => ['/api/top-products'],
            'recent activities' => ['/api/recent-activities'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_anonymous_users_receive_json_401(string $uri): void
    {
        $this->getJson($uri)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_anonymous_users_cannot_read_profiles(): void
    {
        $user = User::factory()->buyer()->create();

        $this->getJson('/api/profiles/'.$user->id)->assertUnauthorized();
    }

    public function test_website_session_cookie_authenticates_stateful_api_requests(): void
    {
        $buyer = User::factory()->buyer()->create([
            'email' => 'session-auth@example.test',
            'password' => Hash::make('session-password'),
        ]);
        $otherBuyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();

        $ownOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'SESSION-OWN-ORDER',
        ]);

        $otherOrder = Order::factory()->create([
            'buyer_id' => $otherBuyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'SESSION-OTHER-ORDER',
        ]);

        $statefulOrigin = 'http://'.config('sanctum.stateful.0');

        $loginResponse = $this->post(route('login.submit'), [
            'login_id' => $buyer->email,
            'password' => 'session-password',
        ]);

        $loginResponse
            ->assertRedirect(route('marketplace'))
            ->assertCookie(config('session.cookie'));

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'));

        $this->assertNotNull($sessionCookie);

        $this->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->withCredentials()
            ->getJson('/api/orders', ['Origin' => $statefulOrigin])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ownOrder->order_number)
            ->assertJsonMissing(['order_number' => $otherOrder->order_number]);
    }

    public function test_buyers_only_see_their_own_orders(): void
    {
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();

        $ownOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'OWN-BUYER-1',
        ]);

        $otherOrder = Order::factory()->create([
            'buyer_id' => $otherBuyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'OTHER-BUYER-1',
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ownOrder->order_number)
            ->assertJsonMissing(['order_number' => $otherOrder->order_number]);
    }

    public function test_buyers_cannot_widen_their_scope_with_query_parameters(): void
    {
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();

        $ownOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'SCOPED-BUYER-1',
        ]);

        $otherOrder = Order::factory()->create([
            'buyer_id' => $otherBuyer->id,
            'farmer_id' => $otherFarmer->id,
            'order_number' => 'SCOPED-OTHER-1',
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/orders?buyer_id='.$otherBuyer->id.'&farmer_id='.$otherFarmer->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ownOrder->order_number)
            ->assertJsonMissing(['order_number' => $otherOrder->order_number]);
    }

    public function test_farmers_only_see_their_own_orders(): void
    {
        $buyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();

        $ownOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'OWN-FARMER-1',
        ]);

        $otherOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $otherFarmer->id,
            'order_number' => 'OTHER-FARMER-1',
        ]);

        Sanctum::actingAs($farmer);

        $this->getJson('/api/orders?farmer_id='.$otherFarmer->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ownOrder->order_number)
            ->assertJsonMissing(['order_number' => $otherOrder->order_number]);
    }

    public function test_bulk_orders_are_scoped_to_the_viewer(): void
    {
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();

        $ownBulkOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'bulk',
            'order_number' => 'OWN-BULK-1',
        ]);

        Order::factory()->create([
            'buyer_id' => $otherBuyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'bulk',
            'order_number' => 'OTHER-BULK-1',
        ]);

        Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'order_number' => 'OWN-STANDARD-1',
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/bulk-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ownBulkOrder->order_number)
            ->assertJsonMissing(['order_number' => 'OTHER-BULK-1'])
            ->assertJsonMissing(['order_number' => 'OWN-STANDARD-1']);
    }

    public function test_farmers_only_see_their_own_bulk_orders(): void
    {
        $buyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();

        $ownBulkOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'bulk',
            'order_number' => 'OWN-FARMER-BULK-1',
        ]);

        $otherBulkOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $otherFarmer->id,
            'order_type' => 'bulk',
            'order_number' => 'OTHER-FARMER-BULK-1',
        ]);

        Sanctum::actingAs($farmer);

        $this->getJson('/api/bulk-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $ownBulkOrder->order_number)
            ->assertJsonMissing(['order_number' => $otherBulkOrder->order_number]);
    }

    public function test_administrators_see_all_orders_and_can_filter_by_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->buyer()->create();
        $otherBuyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();

        $buyerOrder = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'ADMIN-BUYER-1',
        ]);

        Order::factory()->create([
            'buyer_id' => $otherBuyer->id,
            'farmer_id' => $farmer->id,
            'order_number' => 'ADMIN-OTHER-1',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/orders?buyer_id='.$buyer->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_number', $buyerOrder->order_number);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function orderEndpoints(): array
    {
        return [
            'orders' => ['/api/orders'],
            'bulk orders' => ['/api/bulk-orders'],
        ];
    }

    #[DataProvider('orderEndpoints')]
    public function test_users_with_unexpected_roles_cannot_access_order_endpoints(string $uri): void
    {
        // The users table constrains roles, so build an unsaved principal to
        // exercise the controller's defensive branch for an unsupported role.
        $user = User::factory()->make(['role' => 'supplier']);

        Sanctum::actingAs($user);

        $this->getJson($uri)
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['message' => 'Unsupported account role.']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'buyer' => ['buyer'],
            'farmer' => ['farmer'],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_administrators_cannot_access_admin_analytics(string $role): void
    {
        $user = match ($role) {
            'farmer' => User::factory()->farmer()->create(),
            default => User::factory()->buyer()->create(),
        };

        Sanctum::actingAs($user);

        $this->getJson('/api/sales-data')->assertForbidden();
        $this->getJson('/api/top-products')->assertForbidden();
        $this->getJson('/api/recent-activities')->assertForbidden();
    }

    public function test_administrators_can_access_admin_analytics(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();
        $product = Product::factory()->create(['user_id' => $farmer->id]);

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'total_amount' => 250.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 50.00,
            'subtotal' => 150.00,
        ]);

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'api.security.test',
            'description' => 'Admin-only activity',
            'ip_address' => '127.0.0.1',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/sales-data')
            ->assertOk()
            ->assertJsonPath('data.0.order_count', 1);

        $this->getJson('/api/top-products')
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.total_quantity_sold', 3);

        $this->getJson('/api/recent-activities')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'api.security.test')
            ->assertJsonPath('data.0.user.id', $admin->id);
    }

    public function test_profile_private_fields_are_only_visible_to_the_owner_and_administrators(): void
    {
        $owner = User::factory()->buyer()->create([
            'email' => 'owner@example.test',
            'phone_number' => '09170000001',
            'gender' => 'female',
            'birthday' => '1990-01-02',
            'address' => 'Owner Address',
        ]);

        $admin = User::factory()->admin()->create();
        $other = User::factory()->buyer()->create();

        Sanctum::actingAs($owner);
        $this->getJson('/api/profiles/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@example.test')
            ->assertJsonPath('data.phone_number', '09170000001')
            ->assertJsonPath('data.birthday', '1990-01-02')
            ->assertJsonPath('data.address', 'Owner Address');

        Sanctum::actingAs($admin);
        $this->getJson('/api/profiles/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@example.test');

        Sanctum::actingAs($other);
        $response = $this->getJson('/api/profiles/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('data.name', $owner->name);

        $response->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.phone_number')
            ->assertJsonMissingPath('data.gender')
            ->assertJsonMissingPath('data.birthday')
            ->assertJsonMissingPath('data.address');
    }

    public function test_public_product_responses_hide_farmer_phone_and_internal_fields(): void
    {
        $farmer = User::factory()->farmer()->create([
            'phone_number' => '09178889999',
            'address' => 'Farm Road',
            'bio' => 'Organic grower',
        ]);

        $product = Product::factory()->create([
            'user_id' => $farmer->id,
            'name' => 'Public Cabbage',
            'quantity' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Public Cabbage')
            ->assertJsonPath('data.0.farmer.id', $farmer->id)
            ->assertJsonPath('data.0.farmer.name', $farmer->name)
            ->assertJsonPath('data.0.category.slug', $product->category->slug);

        $response->assertJsonMissingPath('data.0.farmer.phone_number')
            ->assertJsonMissingPath('data.0.farmer.email')
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.category_id')
            ->assertJsonMissing(['phone_number' => '09178889999']);
    }

    public function test_public_product_detail_hides_farmer_phone(): void
    {
        $farmer = User::factory()->farmer()->create([
            'phone_number' => '09177778888',
        ]);

        $product = Product::factory()->create([
            'user_id' => $farmer->id,
            'quantity' => 5,
            'is_active' => true,
        ]);

        $this->getJson('/api/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonMissingPath('data.farmer.phone_number')
            ->assertJsonMissing(['phone_number' => '09177778888']);
    }

    public function test_public_recipe_responses_use_an_explicit_whitelist(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Public Recipe']);
        RecipeIngredient::factory()->create([
            'recipe_id' => $recipe->id,
            'name' => 'Tomato',
        ]);

        $this->getJson('/api/recipes')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Public Recipe')
            ->assertJsonPath('data.0.ingredients.0.name', 'Tomato')
            ->assertJsonMissingPath('data.0.user_id');
    }

    public function test_public_recipe_matches_hide_farmer_phone_numbers(): void
    {
        $farmer = User::factory()->farmer()->create([
            'phone_number' => '09176665555',
        ]);

        $product = Product::factory()->create([
            'user_id' => $farmer->id,
            'name' => 'Matchable Tomato',
            'quantity' => 5,
            'is_active' => true,
        ]);

        $recipe = Recipe::factory()->create();

        RecipeIngredient::factory()->create([
            'recipe_id' => $recipe->id,
            'name' => 'Matchable Tomato',
            'category_id' => null,
        ]);

        $response = $this->getJson('/api/recipes/'.$recipe->id.'/match');

        $response->assertOk()
            ->assertJsonPath('ingredients.0.matched_products.0.name', $product->name)
            ->assertJsonMissingPath('ingredients.0.matched_products.0.phone_number')
            ->assertJsonMissing(['phone_number' => '09176665555']);
    }

    public function test_authenticated_users_can_read_the_farmer_directory_without_phone_numbers(): void
    {
        $farmer = User::factory()->farmer()->create([
            'phone_number' => '09170001111',
            'address' => 'Directory Farm',
        ]);

        Sanctum::actingAs(User::factory()->buyer()->create());

        $response = $this->getJson('/api/farmer-locations');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $farmer->id)
            ->assertJsonPath('data.0.address', 'Directory Farm');

        $response->assertJsonMissingPath('data.0.phone_number')
            ->assertJsonMissing(['phone_number' => '09170001111']);
    }

    public function test_api_user_endpoint_returns_only_the_authenticated_user(): void
    {
        $user = User::factory()->buyer()->create([
            'email' => 'self@example.test',
            'phone_number' => '09170002222',
        ]);

        User::factory()->farmer()->create([
            'email' => 'other@example.test',
            'phone_number' => '09170003333',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', 'self@example.test')
            ->assertJsonPath('phone_number', '09170002222')
            ->assertJsonMissing(['email' => 'other@example.test'])
            ->assertJsonMissing(['phone_number' => '09170003333'])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    public function test_authenticated_rate_limiting_is_keyed_by_user_identity(): void
    {
        $firstUser = User::factory()->buyer()->create();
        $secondUser = User::factory()->buyer()->create();

        Sanctum::actingAs($firstUser);

        for ($request = 0; $request < 60; $request++) {
            $this->getJson('/api/orders')->assertOk();
        }

        $this->getJson('/api/orders')->assertStatus(429);

        Sanctum::actingAs($secondUser);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60');
    }

    public function test_api_requests_are_rate_limited(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '60');

        for ($request = 1; $request < 60; $request++) {
            $this->getJson('/api/products')->assertOk();
        }

        $this->getJson('/api/products')->assertStatus(429);
    }
}
