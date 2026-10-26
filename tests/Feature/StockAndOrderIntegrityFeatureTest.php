<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StockAndOrderIntegrityFeatureTest extends TestCase
{
    public function test_checkout_uses_database_prices_and_product_owner_instead_of_session_data(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, [
            'price' => 100.00,
            'quantity' => 10,
        ]);

        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => 'Tampered product name',
                'price' => 1.00,
                'farmer_id' => 999999,
                'quantity' => 2,
                'order_type' => 'standard',
            ],
        ];

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), [
                'delivery_method' => 'deliver',
                'delivery_address' => 'Test Delivery Street, Surigao City',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ]);

        $response->assertRedirect(route('buyer.orders'));
        $response->assertSessionHas('success');

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
        $this->assertSame($farmer->id, $order->farmer_id);
        $this->assertEquals(0.00, (float) $order->shipping_fee);
        $this->assertEquals(200.00, (float) $order->total_amount);
        $this->assertEquals(200.00, (float) $order->payment->amount);

        $item = $order->items()->firstOrFail();
        $this->assertEquals(100.00, (float) $item->unit_price);
        $this->assertSame(8, $product->fresh()->quantity);
    }

    public function test_checkout_rejects_order_that_exceeds_current_stock_without_creating_an_order(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 1]);

        $cart = [
            $product->id => [
                'id' => $product->id,
                'quantity' => 2,
            ],
        ];

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()->quantity);
    }

    public function test_buyer_cancellation_restocks_once_and_cannot_be_repeated(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 10]);
        $order = $this->createOrder($buyer, $farmer, $product, 2);

        $response = $this->actingAs($buyer)->post(route('buyer.orders.cancel', $order));

        $response->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(12, $product->fresh()->quantity);

        $response = $this->actingAs($buyer)->post(route('buyer.orders.cancel', $order));

        $response->assertSessionHas('error');
        $this->assertSame(12, $product->fresh()->quantity);
    }

    public function test_deleting_a_cancelled_order_removes_its_items_but_keeps_the_product(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product, 1, 'cancelled');

        $response = $this->actingAs($buyer)->delete(route('buyer.orders.destroy', $order));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_farmer_cancellation_restocks_once_and_cannot_be_repeated(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 10]);
        $order = $this->createOrder($buyer, $farmer, $product, 3, 'confirmed');

        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'cancelled',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(13, $product->fresh()->quantity);

        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'cancelled',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(13, $product->fresh()->quantity);
    }

    public function test_farmer_cannot_skip_required_order_status_transitions(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 10]);
        $order = $this->createOrder($buyer, $farmer, $product, 1);

        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'delivered',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->quantity);
    }

    public function test_marketplace_listings_exclude_products_with_zero_stock(): void
    {
        $farmer = User::factory()->farmer()->create();
        $category = Category::factory()->create();

        $available = $this->createProduct($farmer, [
            'category_id' => $category->id,
            'name' => 'Visible Harvest Squash',
            'quantity' => 2,
        ]);
        $soldOut = $this->createProduct($farmer, [
            'category_id' => $category->id,
            'name' => 'Hidden Harvest Squash',
            'quantity' => 0,
        ]);

        $marketplace = $this->get(route('marketplace'));
        $marketplace->assertOk();
        $marketplace->assertSee($available->name);
        $marketplace->assertDontSee($soldOut->name);

        $farmerMarketplace = $this->actingAs($farmer)->get(route('farmer.marketplace'));
        $farmerMarketplace->assertOk();
        $farmerMarketplace->assertSee($available->name);
        $farmerMarketplace->assertDontSee($soldOut->name);

        $comparison = $this->get(route('marketplace.compare', ['category_id' => $category->id]));
        $comparison->assertOk();
        $comparison->assertSee($available->name);
        $comparison->assertDontSee($soldOut->name);

        $api = $this->getJson('/api/products');
        $api->assertOk();
        $api->assertJsonFragment(['name' => $available->name]);
        $api->assertJsonMissing(['name' => $soldOut->name]);

        $this->get(route('marketplace.show', $soldOut))->assertNotFound();
        $this->getJson('/api/products/'.$soldOut->id)->assertNotFound();
    }

    public function test_out_of_stock_product_cannot_be_added_to_cart_or_checked_out(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 0]);

        $this->actingAs($buyer)
            ->post(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertSessionHas('error');

        $this->assertNull(session('cart'));

        $cart = [
            $product->id => [
                'id' => $product->id,
                'quantity' => 1,
            ],
        ];

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $product->fresh()->quantity);
    }

    public function test_pending_order_does_not_hide_product_while_stock_remains(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, [
            'name' => 'Still Available After Partial Order',
            'quantity' => 3,
        ]);

        $cart = [
            $product->id => [
                'id' => $product->id,
                'quantity' => 1,
            ],
        ];

        $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ])
            ->assertRedirect(route('buyer.orders'))
            ->assertSessionHas('success');

        $this->assertSame(2, $product->fresh()->quantity);

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->get(route('marketplace'))->assertSee($product->name);
    }

    public function test_cancelling_an_order_restores_out_of_stock_product_visibility(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, [
            'name' => 'Restocked After Cancellation',
            'quantity' => 1,
        ]);

        $cart = [
            $product->id => [
                'id' => $product->id,
                'quantity' => 1,
            ],
        ];

        $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ])
            ->assertRedirect(route('buyer.orders'))
            ->assertSessionHas('success');

        $this->assertSame(0, $product->fresh()->quantity);
        $this->get(route('marketplace'))->assertDontSee($product->name);

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.orders.cancel', $order))
            ->assertSessionHas('success');

        $this->assertSame(1, $product->fresh()->quantity);
        $this->get(route('marketplace'))->assertSee($product->name);
    }

    public function test_farmer_restocking_restores_marketplace_visibility(): void
    {
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct($farmer, [
            'name' => 'Restockable Cabbage',
            'quantity' => 0,
        ]);

        $this->get(route('marketplace'))->assertDontSee($product->name);

        $this->actingAs($farmer)
            ->get(route('farmer.products'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->stock_with_unit);

        $this->actingAs($farmer)
            ->put(route('farmer.products.update', $product), [
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price' => $product->price,
                'quantity' => 20,
                'unit' => 'kg',
                'description' => 'Restocked by the farmer.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(20, $product->fresh()->quantity);
        $this->get(route('marketplace'))->assertSee($product->name);
    }

    public function test_out_of_stock_product_history_remains_accessible_to_buyer_farmer_and_admin(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct($farmer, [
            'name' => 'Historic Out Of Stock Crop',
            'quantity' => 0,
        ]);
        $order = $this->createOrder($buyer, $farmer, $product, 1, 'delivered');

        $this->actingAs($buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee($product->name);

        $this->actingAs($farmer)
            ->get(route('farmer.orders'))
            ->assertOk()
            ->assertSee($product->name);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($product->name);

        $item = $order->items()->firstOrFail();
        $this->assertSame($product->name, $item->product?->name);
        $this->assertEquals(100.00, (float) $item->unit_price);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 0]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_reorder_merges_quantities_and_refreshes_product_metadata(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, [
            'price' => 150.00,
            'quantity' => 20,
            'unit' => 'crate',
            'kilograms' => 12.50,
        ]);

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'bulk',
            'total_amount' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'subtotal' => 100.00,
        ]);

        $cart = [
            $product->id => [
                'id' => $product->id,
                'quantity' => 1,
                'price' => 1.00,
            ],
        ];

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('buyer.orders.reorder', $order));

        $response->assertSessionHas('success');

        $cartItem = session('cart')[$product->id];
        $this->assertSame(3, $cartItem['quantity']);
        $this->assertEquals(150.00, $cartItem['price']);
        $this->assertSame('crate', $cartItem['unit']);
        $this->assertEquals(12.50, $cartItem['weight_per_unit']);
        $this->assertTrue($cartItem['has_weight']);
        $this->assertSame('crates', $cartItem['stock_unit_label']);
        $this->assertSame('crate', $cartItem['price_unit_label']);
        $this->assertSame('bulk', $cartItem['order_type']);
        $this->assertSame($farmer->id, $cartItem['farmer_id']);
    }

    public function test_recipe_batch_add_ignores_unchecked_items_and_checks_combined_stock(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, [
            'price' => 100.00,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($buyer)->post(route('recipes.add-batch'), [
            'products' => [
                ['quantity' => 1],
                ['id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success');

        $cart = session('cart');
        $this->assertSame(2, $cart[$product->id]['quantity']);
        $this->assertEquals(100.00, $cart[$product->id]['price']);

        $response = $this->actingAs($buyer)->post(route('recipes.add-batch'), [
            'products' => [
                ['id' => $product->id, 'quantity' => 4],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(2, session('cart')[$product->id]['quantity']);
    }

    public function test_recipe_batch_add_requires_at_least_one_selected_ingredient(): void
    {
        $buyer = User::factory()->buyer()->create();

        $response = $this->actingAs($buyer)->post(route('recipes.add-batch'), [
            'products' => [
                ['quantity' => 1],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(session('cart'));
    }

    public function test_product_with_order_history_cannot_be_deleted(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $this->createOrder($buyer, $farmer, $product, 1);

        $response = $this->actingAs($farmer)->delete(route('farmer.products.destroy', $product));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        $unusedProduct = $this->createProduct($farmer);

        $response = $this->actingAs($farmer)->delete(route('farmer.products.destroy', $unusedProduct));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('products', ['id' => $unusedProduct->id]);
    }

    public function test_database_restricts_deleting_a_product_that_has_order_items(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $this->createOrder($buyer, $farmer, $product, 1);

        $this->expectException(QueryException::class);

        $product->delete();
    }

    public function test_admin_users_management_routes_are_removed_and_user_records_are_preserved(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product, 1);

        $this->assertFalse(Route::has('admin.users'));
        $this->assertFalse(Route::has('admin.users.delete'));

        $this->get('/admin/users')->assertNotFound();
        $this->actingAs($admin)->get('/admin/users')->assertNotFound();
        $this->actingAs($admin)->delete("/admin/users/{$buyer->id}")->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $buyer->id]);
        $this->assertDatabaseHas('users', ['id' => $farmer->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Manage Users');

        $this->actingAs($admin)->get(route('admin.announcements'))->assertOk();
        $this->actingAs($admin)->get(route('admin.recipes'))->assertOk();

        $this->actingAs($admin)
            ->get(route('marketplace'))
            ->assertOk()
            ->assertDontSee('Manage Users');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProduct(User $farmer, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'user_id' => $farmer->id,
            'category_id' => Category::factory(),
            'price' => 100.00,
            'quantity' => 10,
            'unit' => 'kg',
            'is_active' => true,
        ], $attributes));
    }

    private function createOrder(
        User $buyer,
        User $farmer,
        Product $product,
        int $quantity,
        string $status = 'pending'
    ): Order {
        $shippingFee = 60.00;
        $total = ((float) $product->price * $quantity) + $shippingFee;

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => $quantity >= 20 ? 'bulk' : 'standard',
            'total_amount' => $total,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Test Delivery Street, Surigao City',
            'shipping_fee' => $shippingFee,
            'status' => $status,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'subtotal' => (float) $product->price * $quantity,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $total,
        ]);

        return $order;
    }
}
