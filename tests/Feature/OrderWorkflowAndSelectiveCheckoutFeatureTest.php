<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderWorkflowAndSelectiveCheckoutFeatureTest extends TestCase
{
    public function test_farmer_pickup_order_marks_as_picked_up(): void
    {
        $farmer = User::where('role', 'farmer')->first();
        $buyer = User::where('role', 'buyer')->first();
        $product = Product::where('user_id', $farmer->id)->first();

        $order = Order::create([
            'order_number' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'total_amount' => 150.00,
            'delivery_method' => 'pickup',
            'delivery_address' => 'Pick up at Farm Location',
            'shipping_fee' => 0.00,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 75.00,
            'subtotal' => 150.00,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'amount' => 150.00,
        ]);

        // Farmer confirms order
        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'confirmed',
        ]);
        $response->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('confirmed', $order->status);

        // Farmer marks as picked up (sending delivered or picked_up for pickup order)
        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'delivered',
        ]);
        $response->assertSessionHas('success');
        $order->refresh();

        $this->assertSame('picked_up', $order->status);
        $this->assertSame('Picked Up', $order->status_label);
        $this->assertSame('paid', $order->payment->payment_status);
    }

    public function test_farmer_can_adjust_shipping_fee_when_confirming_delivery_order(): void
    {
        $farmer = User::where('role', 'farmer')->first();
        $buyer = User::where('role', 'buyer')->first();
        $product = Product::where('user_id', $farmer->id)->first();

        $order = Order::create([
            'order_number' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'total_amount' => 260.00,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Brgy. Washington, Surigao City',
            'shipping_fee' => 60.00,
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'subtotal' => 200.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'amount' => 260.00,
        ]);

        // Farmer confirms order and specifies custom distance-based shipping fee of 40.00
        $response = $this->actingAs($farmer)->post(route('farmer.orders.status', $order), [
            'status' => 'confirmed',
            'shipping_fee' => 40.00,
        ]);

        $response->assertSessionHas('success');
        $order->refresh();
        $payment->refresh();

        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(40.00, (float) $order->shipping_fee);
        $this->assertEquals(240.00, (float) $order->total_amount);
        $this->assertEquals(240.00, (float) $payment->amount);
    }

    public function test_buyer_can_delete_cancelled_order(): void
    {
        $farmer = User::where('role', 'farmer')->first();
        $buyer = User::where('role', 'buyer')->first();

        $order = Order::create([
            'order_number' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'total_amount' => 100.00,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Surigao City',
            'shipping_fee' => 60.00,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($buyer)->delete(route('buyer.orders.destroy', $order));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_buyer_cannot_delete_non_cancelled_order(): void
    {
        $farmer = User::where('role', 'farmer')->first();
        $buyer = User::where('role', 'buyer')->first();

        $order = Order::create([
            'order_number' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'total_amount' => 100.00,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Surigao City',
            'shipping_fee' => 60.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($buyer)->delete(route('buyer.orders.destroy', $order));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_buyer_can_bulk_delete_cancelled_orders(): void
    {
        $farmer = User::where('role', 'farmer')->first();
        $buyer = User::where('role', 'buyer')->first();

        $order1 = Order::create([
            'order_number' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'total_amount' => 80.00,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Surigao City',
            'shipping_fee' => 60.00,
            'status' => 'cancelled',
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(5)),
            'buyer_id' => $buyer->id,
            'farmer_id' => $farmer->id,
            'order_type' => 'standard',
            'total_amount' => 90.00,
            'delivery_method' => 'pickup',
            'delivery_address' => 'Farm',
            'shipping_fee' => 0.00,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($buyer)->post(route('buyer.orders.bulkDelete'), [
            'order_ids' => [$order1->id, $order2->id],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('orders', ['id' => $order1->id]);
        $this->assertDatabaseMissing('orders', ['id' => $order2->id]);
    }

    public function test_selective_cart_checkout_orders_only_selected_items_and_preserves_unselected(): void
    {
        $buyer = User::where('role', 'buyer')->first();
        $farmer = User::where('role', 'farmer')->first();

        $products = Product::where('user_id', $farmer->id)->where('quantity', '>=', 5)->take(2)->get();
        if ($products->count() < 2) {
            $this->markTestSkipped('Need at least 2 farmer products with stock for this test.');
        }

        $prod1 = $products[0];
        $prod2 = $products[1];

        $initialStock1 = $prod1->quantity;
        $initialStock2 = $prod2->quantity;

        // Populate session cart with both products
        $cart = [
            $prod1->id => [
                'id' => $prod1->id,
                'name' => $prod1->name,
                'price' => (float) $prod1->price,
                'kilograms' => (float) $prod1->effective_kilograms,
                'unit' => $prod1->unit,
                'farmer_name' => $farmer->name,
                'farmer_id' => $farmer->id,
                'farmer_address' => $farmer->address,
                'quantity' => 2,
                'order_type' => 'standard',
            ],
            $prod2->id => [
                'id' => $prod2->id,
                'name' => $prod2->name,
                'price' => (float) $prod2->price,
                'kilograms' => (float) $prod2->effective_kilograms,
                'unit' => $prod2->unit,
                'farmer_name' => $farmer->name,
                'farmer_id' => $farmer->id,
                'farmer_address' => $farmer->address,
                'quantity' => 3,
                'order_type' => 'standard',
            ],
        ];

        // Buyer only selects product 1 to place order
        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $cart])
            ->post(route('checkout.place'), [
                'delivery_method' => 'deliver',
                'delivery_address' => 'Barangay San Juan, Surigao City',
                'payment_method' => 'cod',
                'selected_items' => [$prod1->id],
            ]);

        $response->assertRedirect(route('buyer.orders'));
        $response->assertSessionHas('success');

        // Check stock: prod1 deducted, prod2 unchanged
        $prod1->refresh();
        $prod2->refresh();
        $this->assertSame($initialStock1 - 2, $prod1->quantity);
        $this->assertSame($initialStock2, $prod2->quantity);

        // Check cart session: prod1 removed, prod2 still in cart
        $sessionCart = session('cart');
        $this->assertIsArray($sessionCart);
        $this->assertArrayNotHasKey($prod1->id, $sessionCart);
        $this->assertArrayHasKey($prod2->id, $sessionCart);
        $this->assertSame(3, $sessionCart[$prod2->id]['quantity']);
    }
}
