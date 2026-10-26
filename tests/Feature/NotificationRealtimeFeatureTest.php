<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Notifications\NotificationPayload;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotificationRealtimeFeatureTest extends TestCase
{
    public function test_notification_created_event_broadcasts_on_a_private_user_channel(): void
    {
        $farmer = User::factory()->farmer()->create();

        $farmer->notify(new OrderPlacedNotification(
            orderNumber: 'ORD-20261022-ABCDE',
            buyerName: 'Buyer One',
            totalAmount: 250.00,
            itemCount: 2,
        ));

        $notification = $farmer->notifications()->firstOrFail();
        $event = new NotificationCreated($farmer, $notification);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertSame('private-App.Models.User.'.$farmer->id, $event->broadcastOn()->name);
        $this->assertSame('notification.created', $event->broadcastAs());

        $payload = $event->broadcastWith();

        $this->assertSame([
            'id',
            'title',
            'message',
            'reference',
            'url',
            'icon',
            'category',
            'created_at',
            'read_at',
        ], array_keys($payload));
        $this->assertSame($notification->id, $payload['id']);
        $this->assertSame('New order received', $payload['title']);
        $this->assertSame('ORD-20261022-ABCDE', $payload['reference']);
        $this->assertSame(route('farmer.orders', absolute: false), $payload['url']);
        $this->assertSame('bi-bag-check', $payload['icon']);
        $this->assertSame('order', $payload['category']);
        $this->assertNull($payload['read_at']);
    }

    public function test_notification_feed_returns_only_owned_notifications_in_latest_first_order(): void
    {
        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();

        foreach (range(1, 25) as $minutesAgo) {
            $this->createNotification($farmer, 'reference-'.$minutesAgo, $minutesAgo);
        }

        $this->createNotification($otherFarmer, 'other-user', 1);

        $response = $this->actingAs($farmer)->getJson(route('notifications.feed'));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(20, 'notifications')
            ->assertJsonPath('unread_count', 25)
            ->assertJsonPath('notifications.0.reference', 'reference-1')
            ->assertJsonPath('notifications.19.reference', 'reference-20')
            ->assertJsonMissing(['reference' => 'other-user']);

        $this->assertSame(25, $farmer->notifications()->count());
        $this->assertSame(25, $farmer->unreadNotifications()->count());
    }

    public function test_reading_a_notification_as_json_returns_the_updated_unread_count_and_redirect(): void
    {
        $farmer = User::factory()->farmer()->create();
        $notification = $this->createNotification($farmer, 'ORD-20261022-ABCDE');

        $response = $this->actingAs($farmer)->postJson(
            route('notifications.read', $notification),
            ['redirect_to' => route('farmer.orders', absolute: false)],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('redirect_url', route('farmer.orders', absolute: false))
            ->assertJsonPath('notification.id', $notification->id)
            ->assertJsonPath('notification.read_at', fn ($readAt): bool => is_string($readAt));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_reading_a_notification_as_json_ignores_an_unsafe_redirect(): void
    {
        $farmer = User::factory()->farmer()->create();
        $notification = $this->createNotification($farmer, 'ORD-20261022-ABCDE');

        $response = $this->actingAs($farmer)->postJson(
            route('notifications.read', $notification),
            ['redirect_to' => 'https://example.com/phishing'],
        );

        $response->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('redirect_url', null);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_all_notifications_as_read_as_json_clears_the_unread_count(): void
    {
        $farmer = User::factory()->farmer()->create();

        $this->createNotification($farmer, 'first');
        $this->createNotification($farmer, 'second');

        $response = $this->actingAs($farmer)->postJson(route('notifications.read-all'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $farmer->unreadNotifications()->count());
        $this->assertSame(2, $farmer->notifications()->count());
    }

    public function test_private_notification_channel_only_authorizes_its_owner(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options' => [
                'host' => 'localhost',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ]);

        // Channel authorizations are registered on the broadcaster instance that
        // existed during boot. Re-register them after switching the test driver.
        Broadcast::purge();
        require base_path('routes/channels.php');

        $farmer = User::factory()->farmer()->create();
        $otherFarmer = User::factory()->farmer()->create();

        $this->post('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$farmer->id,
        ])->assertForbidden();

        $this->actingAs($farmer)
            ->post('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-App.Models.User.'.$farmer->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($farmer)
            ->post('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-App.Models.User.'.$otherFarmer->id,
            ])
            ->assertForbidden();
    }

    public function test_notification_payload_keeps_safe_internal_links(): void
    {
        $this->assertSame('/farmer/orders', NotificationPayload::safeUrl('/farmer/orders'));
        $this->assertSame('/orders?filter=pending', NotificationPayload::safeUrl('/orders?filter=pending'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeNotificationUrls(): array
    {
        return [
            'absolute url' => ['https://example.com/phishing'],
            'protocol relative url' => ['//example.com/phishing'],
            'backslash url' => ['/\\example.com/phishing'],
            'javascript url' => ['javascript:alert(1)'],
            'header injection' => ["/orders\r\nLocation: https://example.com"],
            'empty url' => [''],
        ];
    }

    #[DataProvider('unsafeNotificationUrls')]
    public function test_notification_payload_removes_unsafe_links(string $url): void
    {
        $this->assertNull(NotificationPayload::safeUrl($url));
    }

    private function createNotification(User $user, string $reference, int $minutesAgo = 0): DatabaseNotification
    {
        $createdAt = now()->subMinutes($minutesAgo);

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => LowStockNotification::class,
            'data' => [
                'title' => 'Low stock alert',
                'message' => "{$reference} is running low.",
                'reference' => $reference,
                'url' => route('farmer.products', absolute: false),
                'icon' => 'bi-exclamation-triangle',
                'category' => 'inventory',
            ],
            'read_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
