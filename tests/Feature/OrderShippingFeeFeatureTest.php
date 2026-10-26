<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderShippingFeeFeatureTest extends TestCase
{
    public function test_delivery_checkout_starts_with_zero_shipping_and_subtotal_total(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, [
            'price' => 100.00,
            'quantity' => 10,
        ]);
        $cart = $this->cartFor($product, 2);

        $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Initial shipping fee is ₱0.00')
            ->assertDontSee('₱60.00 ×');

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

        $this->assertSame('pending', $order->status);
        $this->assertEquals(0.00, (float) $order->shipping_fee);
        $this->assertEquals(200.00, (float) $order->total_amount);
        $this->assertEquals(200.00, (float) $order->payment->amount);
    }

    public function test_farmer_sets_shipping_fee_and_total_is_recalculated_from_items_subtotal(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product);

        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'confirmed',
            'shipping_fee' => '40.50',
        ]);

        $response->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(40.50, (float) $order->shipping_fee);
        $this->assertEquals(240.50, (float) $order->total_amount);
        $this->assertEquals(240.50, (float) $order->payment->amount);
        $this->assertSame('pending', $order->payment->payment_status);

        $this->assertSame(1, $buyer->notifications()->count());

        $notification = $buyer->notifications()->firstOrFail();

        $this->assertStringContainsString('Shipping fee: ₱40.50', $notification->data['message']);
        $this->assertStringContainsString('Updated total: ₱240.50', $notification->data['message']);
    }

    public function test_other_farmer_and_buyer_cannot_change_shipping_fee(): void
    {
        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product);

        $this->actingAs($otherFarmer)
            ->post(route('farmer.orders.status', $order), [
                'status' => 'confirmed',
                'shipping_fee' => 40.00,
            ])
            ->assertForbidden();

        $this->actingAs($buyer)
            ->post(route('farmer.orders.status', $order), [
                'status' => 'confirmed',
                'shipping_fee' => 40.00,
            ])
            ->assertRedirect(route('marketplace'))
            ->assertSessionHas('error');

        $order->refresh();

        $this->assertSame('pending', $order->status);
        $this->assertEquals(0.00, (float) $order->shipping_fee);
        $this->assertEquals(200.00, (float) $order->total_amount);
        $this->assertSame(0, $buyer->notifications()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidShippingFees(): array
    {
        return [
            'negative' => ['-0.01'],
            'non numeric' => ['abc'],
            'more than two decimals' => ['40.123'],
            'above maximum' => ['1000.01'],
        ];
    }

    #[DataProvider('invalidShippingFees')]
    public function test_invalid_shipping_fee_is_rejected(string $shippingFee): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product);

        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'confirmed',
            'shipping_fee' => $shippingFee,
        ]);

        $response->assertSessionHasErrors('shipping_fee');

        $order->refresh();

        $this->assertSame('pending', $order->status);
        $this->assertEquals(0.00, (float) $order->shipping_fee);
        $this->assertEquals(200.00, (float) $order->total_amount);
        $this->assertSame(0, $buyer->notifications()->count());
    }

    public function test_buyer_sees_updated_shipping_fee_and_total(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product);

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), [
                'status' => 'confirmed',
                'shipping_fee' => 40.00,
            ])
            ->assertSessionHas('success');

        $this->actingAs($buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('Shipping Fee:')
            ->assertSee('₱40.00')
            ->assertSee('₱240.00')
            ->assertSee('Set by the farmer before delivery.');
    }

    public function test_placing_new_order_does_not_change_existing_order_shipping_fees_or_totals(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $existingOrder = $this->createOrder($buyer, $farmer, $product, shippingFee: 60.00);

        $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product, 3)])
            ->post(route('checkout.place'), [
                'delivery_method' => 'deliver',
                'delivery_address' => 'Test Delivery Street, Surigao City',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ])
            ->assertSessionHas('success');

        $existingOrder->refresh();

        $this->assertEquals(60.00, (float) $existingOrder->shipping_fee);
        $this->assertEquals(260.00, (float) $existingOrder->total_amount);

        $newOrder = Order::where('buyer_id', $buyer->id)
            ->whereKeyNot($existingOrder->id)
            ->firstOrFail();

        $this->assertEquals(0.00, (float) $newOrder->shipping_fee);
        $this->assertEquals(300.00, (float) $newOrder->total_amount);
    }

    public function test_shipping_fee_cannot_be_changed_after_payment_is_paid(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product, shippingFee: 60.00);
        $order->payment->update([
            'payment_status' => 'paid',
            'payment_date' => now(),
        ]);

        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'confirmed',
            'shipping_fee' => 40.00,
        ]);

        $response->assertSessionHas('error');

        $order->refresh();

        $this->assertSame('pending', $order->status);
        $this->assertEquals(60.00, (float) $order->shipping_fee);
        $this->assertEquals(260.00, (float) $order->total_amount);
        $this->assertEquals(260.00, (float) $order->payment->amount);
        $this->assertSame('paid', $order->payment->payment_status);
        $this->assertSame(0, $buyer->notifications()->count());
    }

    public function test_paid_order_can_be_confirmed_without_changing_shipping_fee(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product, shippingFee: 60.00);
        $order->payment->update([
            'payment_status' => 'paid',
            'payment_date' => now(),
        ]);

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), [
                'status' => 'confirmed',
                'shipping_fee' => 60.00,
            ])
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(60.00, (float) $order->shipping_fee);
        $this->assertEquals(260.00, (float) $order->total_amount);
        $this->assertEquals(260.00, (float) $order->payment->amount);
        $this->assertSame('paid', $order->payment->payment_status);
    }

    public function test_delivery_completion_marks_payment_paid_after_farmer_sets_fee(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product);

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), [
                'status' => 'confirmed',
                'shipping_fee' => 40.00,
            ])
            ->assertSessionHas('success');

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), [
                'status' => 'delivered',
            ])
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('delivered', $order->status);
        $this->assertSame('paid', $order->payment->payment_status);
        $this->assertEquals(240.00, (float) $order->payment->amount);
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
            'quantity' => 100,
            'unit' => 'kg',
            'is_active' => true,
        ], $attributes));
    }

    private function createOrder(
        User $buyer,
        User $farmer,
        Product $product,
        int $quantity = 2,
        float $shippingFee = 0.00,
    ): Order {
        $subtotal = round((float) $product->price * $quantity, 2);
        $total = round($subtotal + $shippingFee, 2);

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => $quantity >= 20 ? 'bulk' : 'standard',
            'total_amount' => $total,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Test Delivery Street, Surigao City',
            'shipping_fee' => $shippingFee,
            'status' => 'pending',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'subtotal' => $subtotal,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $total,
        ]);

        return $order;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cartFor(Product $product, int $quantity): array
    {
        return [
            $product->id => [
                'id' => $product->id,
                'quantity' => $quantity,
                'farmer_id' => $product->user_id,
                'order_type' => $quantity >= 20 ? 'bulk' : 'standard',
            ],
        ];
    }
}
