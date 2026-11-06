<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class PickupCheckoutFeatureTest extends TestCase
{
    public function test_checkout_page_shows_the_buyers_delivery_address_and_each_farmers_pickup_address(): void
    {
        $farmer = User::factory()->farmer()->create([
            'name' => 'Farmer One',
            'address' => 'Farm One, Pickup Road',
        ]);
        $buyer = User::factory()->buyer()->create([
            'address' => 'Buyer Home Street',
        ]);
        $product = $this->createProduct($farmer);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product)])
            ->get(route('checkout'));

        $response->assertOk()
            ->assertSee('Buyer Home Street')
            ->assertSee('Farm One, Pickup Road')
            ->assertSee('Farmer Pickup Location')
            ->assertDontSee('Pick up at registered farm location');
    }

    public function test_pickup_order_uses_the_farmers_address_and_not_the_buyer_or_submitted_address(): void
    {
        $farmer = User::factory()->farmer()->create([
            'address' => 'Farmer Pickup Lane',
        ]);
        $buyer = User::factory()->buyer()->create([
            'address' => 'Buyer Home Street',
        ]);
        $product = $this->createProduct($farmer);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product)])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'delivery_address' => 'Manipulated Browser Address',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ]);

        $response->assertRedirect(route('buyer.orders'));
        $response->assertSessionHas('success');

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();

        $this->assertSame('pickup', $order->delivery_method);
        $this->assertSame('Farmer Pickup Lane', $order->delivery_address);
        $this->assertSame('Buyer Home Street', $buyer->fresh()->address);
    }

    public function test_delivery_order_uses_the_buyers_submitted_address_and_not_the_farmer_address(): void
    {
        $farmer = User::factory()->farmer()->create([
            'address' => 'Farmer Pickup Lane',
        ]);
        $buyer = User::factory()->buyer()->create([
            'address' => 'Buyer Home Street',
        ]);
        $product = $this->createProduct($farmer);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product)])
            ->post(route('checkout.place'), [
                'delivery_method' => 'deliver',
                'delivery_address' => 'Buyer Submitted Delivery Address',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ]);

        $response->assertRedirect(route('buyer.orders'));
        $response->assertSessionHas('success');

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();

        $this->assertSame('deliver', $order->delivery_method);
        $this->assertSame('Buyer Submitted Delivery Address', $order->delivery_address);
        $this->assertSame('Buyer Home Street', $buyer->fresh()->address);
    }

    public function test_pickup_checkout_assigns_each_farmer_their_own_pickup_address(): void
    {
        $farmerA = User::factory()->farmer()->create([
            'address' => 'Farm A Pickup Road',
        ]);
        $farmerB = User::factory()->farmer()->create([
            'address' => 'Farm B Pickup Road',
        ]);
        $buyer = User::factory()->buyer()->create();
        $productA = $this->createProduct($farmerA, ['name' => 'Farmer A Squash']);
        $productB = $this->createProduct($farmerB, ['name' => 'Farmer B Beans']);

        $response = $this->actingAs($buyer)
            ->withSession([
                'cart' => $this->cartFor($productA) + $this->cartFor($productB),
            ])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$productA->id, $productB->id],
            ]);

        $response->assertRedirect(route('buyer.orders'));
        $response->assertSessionHas('success');

        $orders = Order::where('buyer_id', $buyer->id)->get()->keyBy('farmer_id');

        $this->assertCount(2, $orders);
        $this->assertSame('Farm A Pickup Road', $orders[$farmerA->id]->delivery_address);
        $this->assertSame('Farm B Pickup Road', $orders[$farmerB->id]->delivery_address);
    }

    public function test_pickup_checkout_is_rejected_when_a_farmer_has_no_pickup_address(): void
    {
        $farmer = User::factory()->farmer()->create([
            'address' => null,
        ]);
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 5]);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product)])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->fresh()->quantity);
    }

    public function test_checkout_page_warns_when_a_farmer_has_no_pickup_address(): void
    {
        $farmer = User::factory()->farmer()->create([
            'address' => null,
        ]);
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product)])
            ->get(route('checkout'));

        $response->assertOk()
            ->assertSee('has not provided a pickup address')
            ->assertSee('Pickup cannot be completed');
    }

    public function test_cart_payload_and_cart_page_do_not_expose_the_farmer_address(): void
    {
        $farmer = User::factory()->farmer()->create([
            'address' => 'Private Farm Address 987',
        ]);
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);

        $this->actingAs($buyer)->post(route('cart.add'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $cart = session('cart');

        $this->assertIsArray($cart);
        $this->assertArrayHasKey($product->id, $cart);
        $this->assertArrayNotHasKey('farmer_address', $cart[$product->id]);

        $this->actingAs($buyer)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('Private Farm Address 987');
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cartFor(Product $product): array
    {
        return [
            $product->id => [
                'id' => $product->id,
                'quantity' => 1,
                'farmer_id' => $product->user_id,
                'order_type' => 'standard',
            ],
        ];
    }
}
