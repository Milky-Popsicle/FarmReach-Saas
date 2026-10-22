<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationWorkflowFeatureTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_notification_routes(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->getJson(route('notifications.feed'))->assertUnauthorized();

        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }

    public function test_checkout_notifies_only_the_farmer_who_owns_each_product(): void
    {
        $farmerA = User::factory()->farmer()->create();
        $farmerB = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $productA = $this->createProduct($farmerA);
        $productB = $this->createProduct($farmerB);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($productA, 2) + $this->cartFor($productB, 3)])
            ->post(route('checkout.place'), [
                'delivery_method' => 'deliver',
                'delivery_address' => 'Test Delivery Street, Surigao City',
                'payment_method' => 'cod',
                'selected_items' => [$productA->id, $productB->id],
            ]);

        $response->assertRedirect(route('buyer.orders'));
        $response->assertSessionHas('success');

        $this->assertSame(1, $farmerA->notifications()->count());
        $this->assertSame(1, $farmerB->notifications()->count());
        $this->assertSame(0, $buyer->notifications()->count());

        $this->assertSame(
            OrderPlacedNotification::class,
            $farmerA->notifications()->firstOrFail()->type
        );
        $this->assertSame(
            OrderPlacedNotification::class,
            $farmerB->notifications()->firstOrFail()->type
        );
    }

    public function test_farmer_confirmation_and_completion_notify_the_buyer_and_completion_notifies_the_farmer(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product);

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), ['status' => 'confirmed'])
            ->assertSessionHas('success');

        $this->assertSame(1, $buyer->notifications()->count());
        $this->assertSame(0, $farmer->notifications()->count());
        $this->assertContains('Order confirmed', $this->notificationTitles($buyer));

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), ['status' => 'delivered'])
            ->assertSessionHas('success');

        $this->assertSame(2, $buyer->notifications()->count());
        $this->assertSame(1, $farmer->notifications()->count());
        $this->assertContains('Order Delivered', $this->notificationTitles($buyer));
        $this->assertContains('Order completed', $this->notificationTitles($farmer));

        $this->assertSame(
            OrderStatusChangedNotification::class,
            $farmer->notifications()->firstOrFail()->type
        );
    }

    public function test_buyer_cancellation_notifies_the_farmer_once(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product, 2);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.cancel', $order))
            ->assertSessionHas('success');

        $this->assertSame(1, $farmer->notifications()->count());
        $this->assertSame(0, $buyer->notifications()->count());

        $notification = $farmer->notifications()->firstOrFail();

        $this->assertSame(OrderCancelledNotification::class, $notification->type);
        $this->assertStringContainsString($order->order_number, $notification->data['message']);

        $this->actingAs($buyer)
            ->post(route('buyer.orders.cancel', $order))
            ->assertSessionHas('error');

        $this->assertSame(1, $farmer->notifications()->count());
    }

    public function test_farmer_cancellation_notifies_the_buyer_once(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product, 2, 'confirmed');

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHas('success');

        $this->assertSame(1, $buyer->notifications()->count());
        $this->assertSame(0, $farmer->notifications()->count());
        $this->assertSame(
            OrderCancelledNotification::class,
            $buyer->notifications()->firstOrFail()->type
        );

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), ['status' => 'cancelled'])
            ->assertSessionHas('error');

        $this->assertSame(1, $buyer->notifications()->count());
    }

    public function test_rejected_order_transitions_do_not_create_notifications(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer);
        $order = $this->createOrder($buyer, $farmer, $product);

        $this->actingAs($farmer)
            ->post(route('farmer.orders.status', $order), ['status' => 'delivered'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(0, $farmer->notifications()->count());
        $this->assertSame(0, $buyer->notifications()->count());
    }

    public function test_failed_checkout_does_not_create_notifications(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 1]);

        $response = $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product, 2)])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $farmer->notifications()->count());
        $this->assertSame(0, $buyer->notifications()->count());
    }

    public function test_users_cannot_read_or_mark_another_users_notifications(): void
    {
        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();

        $farmer->notify(new LowStockNotification('Tomatoes', 4, 'kg', 10));

        $notification = $farmer->notifications()->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($buyer)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertNull($notification->fresh()->read_at);
        $this->assertSame(0, $buyer->notifications()->count());

        $this->actingAs($farmer)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_reading_a_notification_marks_it_read_and_redirects_to_its_reference(): void
    {
        $buyer = User::factory()->buyer()->create();

        $buyer->notify(new OrderStatusChangedNotification(
            orderNumber: 'ORD-20261021-ABCDE',
            status: 'confirmed',
            statusLabel: 'Confirmed',
            audience: 'buyer',
            farmerName: 'Farmer One',
            buyerName: $buyer->name,
        ));

        $notification = $buyer->notifications()->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('notifications.read', $notification), [
                'redirect_to' => route('buyer.orders', absolute: false),
            ])
            ->assertRedirect(route('buyer.orders', absolute: false));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeRedirectTargets(): array
    {
        return [
            'absolute url' => ['https://example.com/phishing'],
            'protocol relative url' => ['//example.com/phishing'],
            'backslash url' => ['/\\example.com/phishing'],
        ];
    }

    #[DataProvider('unsafeRedirectTargets')]
    public function test_external_redirect_targets_are_ignored_when_reading_a_notification(string $target): void
    {
        $buyer = User::factory()->buyer()->create();

        $buyer->notify(new LowStockNotification('Tomatoes', 3, 'kg', 10));

        $notification = $buyer->notifications()->firstOrFail();

        $this->from(route('notifications.index'))
            ->actingAs($buyer)
            ->post(route('notifications.read', $notification), ['redirect_to' => $target])
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_history_and_bell_count_unread_notifications(): void
    {
        $farmer = User::factory()->farmer()->create();

        $farmer->notify(new LowStockNotification('Cabbage', 3, 'kg', 10));
        $farmer->notify(new LowStockNotification('Carrots', 2, 'kg', 10));

        $this->actingAs($farmer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Low stock alert')
            ->assertSee('Mark all as read');

        $this->actingAs($farmer)
            ->get(route('marketplace'))
            ->assertOk()
            ->assertSee('2 unread')
            ->assertSee('View all notifications');

        $this->actingAs($farmer)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $farmer->unreadNotifications()->count());
        $this->assertSame(2, $farmer->notifications()->count());

        $this->actingAs($farmer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee("You're all caught up.", false);
    }

    public function test_notification_history_shows_an_empty_state(): void
    {
        $buyer = User::factory()->buyer()->create();

        $this->actingAs($buyer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('No notifications yet');
    }

    public function test_notification_history_escapes_user_provided_content(): void
    {
        $farmer = User::factory()->farmer()->create();

        $farmer->notify(new OrderPlacedNotification(
            orderNumber: 'ORD-20261021-AAAAA',
            buyerName: '<script>alert("xss")</script>',
            totalAmount: 100.00,
            itemCount: 1,
        ));

        $this->actingAs($farmer)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert', false)
            ->assertDontSee('<script>alert', false);
    }

    public function test_low_stock_alert_is_sent_once_and_becomes_eligible_again_after_replenishment(): void
    {
        config(['farmreach.low_stock_threshold' => 10]);

        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 12]);

        $this->placeOrder($buyer, $product, 5);

        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertNotNull($product->fresh()->low_stock_notified_at);
        $this->assertSame(1, $this->lowStockNotificationCount($farmer));

        $this->actingAs($farmer)
            ->put(route('farmer.products.update', $product), $this->productUpdatePayload($product, 8))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->lowStockNotificationCount($farmer));
        $this->assertNotNull($product->fresh()->low_stock_notified_at);

        $this->actingAs($farmer)
            ->put(route('farmer.products.update', $product), $this->productUpdatePayload($product, 30))
            ->assertSessionHas('success');

        $this->assertNull($product->fresh()->low_stock_notified_at);

        $this->placeOrder($buyer, $product->fresh(), 25);

        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame(2, $this->lowStockNotificationCount($farmer));
        $this->assertNotNull($product->fresh()->low_stock_notified_at);
    }

    public function test_farmer_listing_a_low_stock_product_triggers_one_alert_per_low_stock_cycle(): void
    {
        config(['farmreach.low_stock_threshold' => 5]);

        $farmer = User::factory()->farmer()->create();
        $category = Category::factory()->create();

        $this->actingAs($farmer)
            ->post(route('farmer.products.store'), [
                'name' => 'Low Stock Cabbage',
                'category_id' => $category->id,
                'price' => 50.00,
                'quantity' => 5,
                'selling_unit' => 'kg',
            ])
            ->assertSessionHas('success');

        $product = Product::where('name', 'Low Stock Cabbage')->firstOrFail();

        $this->assertNotNull($product->low_stock_notified_at);
        $this->assertSame(1, $this->lowStockNotificationCount($farmer));

        $this->actingAs($farmer)
            ->put(route('farmer.products.update', $product), $this->productUpdatePayload($product, 4))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->lowStockNotificationCount($farmer));

        $this->actingAs($farmer)
            ->put(route('farmer.products.update', $product), $this->productUpdatePayload($product, 20))
            ->assertSessionHas('success');

        $this->assertNull($product->fresh()->low_stock_notified_at);

        $this->actingAs($farmer)
            ->put(route('farmer.products.update', $product), $this->productUpdatePayload($product, 5))
            ->assertSessionHas('success');

        $this->assertSame(2, $this->lowStockNotificationCount($farmer));
    }

    public function test_cancellation_replenishment_resets_low_stock_eligibility(): void
    {
        config(['farmreach.low_stock_threshold' => 10]);

        $farmer = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $product = $this->createProduct($farmer, ['quantity' => 12]);

        $this->placeOrder($buyer, $product, 5);

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();

        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertSame(1, $this->lowStockNotificationCount($farmer));

        $this->actingAs($buyer)
            ->post(route('buyer.orders.cancel', $order))
            ->assertSessionHas('success');

        $this->assertSame(12, $product->fresh()->quantity);
        $this->assertNull($product->fresh()->low_stock_notified_at);
        $this->assertSame(1, $this->lowStockNotificationCount($farmer));

        $this->placeOrder($buyer, $product->fresh(), 5);

        $this->assertSame(2, $this->lowStockNotificationCount($farmer));
    }

    public function test_low_stock_alerts_only_go_to_the_product_owner(): void
    {
        config(['farmreach.low_stock_threshold' => 10]);

        $farmerA = User::factory()->farmer()->create();
        $farmerB = User::factory()->farmer()->create();
        $buyer = User::factory()->buyer()->create();
        $lowStockProduct = $this->createProduct($farmerA, ['quantity' => 6]);
        $healthyProduct = $this->createProduct($farmerB, ['quantity' => 100]);

        $response = $this->actingAs($buyer)
            ->withSession([
                'cart' => $this->cartFor($lowStockProduct, 2) + $this->cartFor($healthyProduct, 2),
            ])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$lowStockProduct->id, $healthyProduct->id],
            ]);

        $response->assertSessionHas('success');

        $this->assertSame(1, $this->lowStockNotificationCount($farmerA));
        $this->assertSame(0, $this->lowStockNotificationCount($farmerB));
        $this->assertNotNull($lowStockProduct->fresh()->low_stock_notified_at);
        $this->assertNull($healthyProduct->fresh()->low_stock_notified_at);
        $this->assertSame(0, $buyer->notifications()->count());
    }

    public function test_admin_accounts_do_not_receive_user_notifications(): void
    {
        $admin = User::factory()->admin()->create();

        $admin->notify(new OrderPlacedNotification('ORD-20261021-AAAAA', 'Buyer One', 100.00, 1));
        $admin->notify(new OrderStatusChangedNotification(
            orderNumber: 'ORD-20261021-AAAAA',
            status: 'confirmed',
            statusLabel: 'Confirmed',
            audience: 'buyer',
            farmerName: 'Farmer One',
            buyerName: 'Buyer One',
        ));
        $admin->notify(new OrderCancelledNotification('ORD-20261021-AAAAA', 'buyer', 'Buyer One'));
        $admin->notify(new LowStockNotification('Tomatoes', 3, 'kg', 10));

        $this->assertSame(0, $admin->notifications()->count());
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

    private function placeOrder(User $buyer, Product $product, int $quantity): void
    {
        $this->actingAs($buyer)
            ->withSession(['cart' => $this->cartFor($product, $quantity)])
            ->post(route('checkout.place'), [
                'delivery_method' => 'pickup',
                'payment_method' => 'cod',
                'selected_items' => [$product->id],
            ])
            ->assertSessionHas('success');
    }

    /**
     * @return array<string, mixed>
     */
    private function productUpdatePayload(Product $product, int $quantity): array
    {
        return [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'price' => $product->price,
            'quantity' => $quantity,
            'selling_unit' => $product->unit,
        ];
    }

    private function lowStockNotificationCount(User $farmer): int
    {
        return $farmer->notifications()
            ->where('type', LowStockNotification::class)
            ->count();
    }

    /**
     * @return array<int, string>
     */
    private function notificationTitles(User $user): array
    {
        return $user->notifications()
            ->get()
            ->map(fn ($notification): string => (string) $notification->data['title'])
            ->all();
    }
}
