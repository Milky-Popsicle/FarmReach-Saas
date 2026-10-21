<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
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
        $this->assertEquals(260.00, (float) $order->total_amount);

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

    public function test_admin_cannot_delete_users_with_order_or_product_history(): void
    {
        $admin = User::factory()->admin()->create();

        $buyer = User::factory()->buyer()->create();
        $farmer = User::factory()->farmer()->create();
        $product = $this->createProduct($farmer);
        $this->createOrder($buyer, $farmer, $product, 1);

        $response = $this->actingAs($admin)->delete(route('admin.users.delete', $buyer));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $buyer->id]);

        $response = $this->actingAs($admin)->delete(route('admin.users.delete', $farmer));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $farmer->id]);

        $cleanBuyer = User::factory()->buyer()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.delete', $cleanBuyer));

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $cleanBuyer->id]);
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
