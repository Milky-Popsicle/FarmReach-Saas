<?php

namespace Tests\Unit\Listeners;

use App\Events\NotificationCreated;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class BroadcastDatabaseNotificationTest extends TestCase
{
    /**
     * This test class explicitly manages transactions instead of using the
     * application test transaction, so post-commit callbacks can be exercised.
     */
    public function refreshDatabase(): void {}

    public function test_database_notifications_are_broadcast_after_the_transaction_commits(): void
    {
        Event::fake([NotificationCreated::class]);

        $user = User::factory()->make(['id' => 42]);
        $notification = $this->databaseNotificationFor($user);
        $sent = new NotificationSent($user, new LowStockNotification('Tomatoes', 4, 'kg', 10), 'database', $notification);

        DB::beginTransaction();

        Event::dispatch($sent);

        Event::assertNotDispatched(NotificationCreated::class);

        DB::commit();

        Event::assertDispatched(
            NotificationCreated::class,
            fn (NotificationCreated $event): bool => $event->user->is($user)
                && $event->notification->is($notification)
                && $event->broadcastOn()->name === 'private-App.Models.User.42',
        );
    }

    public function test_notifications_are_not_broadcast_when_the_transaction_rolls_back(): void
    {
        Event::fake([NotificationCreated::class]);

        $user = User::factory()->make(['id' => 42]);
        $notification = $this->databaseNotificationFor($user);
        $sent = new NotificationSent($user, new LowStockNotification('Tomatoes', 4, 'kg', 10), 'database', $notification);

        try {
            DB::beginTransaction();

            Event::dispatch($sent);
            DB::rollBack();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        Event::assertNotDispatched(NotificationCreated::class);
    }

    public function test_non_database_channels_and_non_user_notifiables_are_ignored(): void
    {
        Event::fake([NotificationCreated::class]);

        $user = User::factory()->make(['id' => 42]);
        $notification = $this->databaseNotificationFor($user);

        Event::dispatch(new NotificationSent($user, new LowStockNotification('Tomatoes', 4, 'kg', 10), 'mail', $notification));
        Event::dispatch(new NotificationSent(new \stdClass, new LowStockNotification('Tomatoes', 4, 'kg', 10), 'database', $notification));
        Event::dispatch(new NotificationSent($user, new LowStockNotification('Tomatoes', 4, 'kg', 10), 'database', ['ok' => true]));

        Event::assertNotDispatched(NotificationCreated::class);
    }

    public function test_broadcast_failures_are_logged_without_breaking_notification_delivery(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $notification = $this->databaseNotificationFor($user);
        $sent = new NotificationSent($user, new LowStockNotification('Tomatoes', 4, 'kg', 10), 'database', $notification);

        Event::listen(NotificationCreated::class, fn () => throw new RuntimeException('Broadcast unavailable.'));
        Log::spy();

        Event::dispatch($sent);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Unable to broadcast notification.', \Mockery::on(
                fn (array $context): bool => $context['notification_id'] === $notification->id
                    && $context['user_id'] === 42,
            ));
    }

    private function databaseNotificationFor(User $user): DatabaseNotification
    {
        $notification = new DatabaseNotification;
        $notification->forceFill([
            'id' => (string) Str::uuid(),
            'type' => LowStockNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->getKey(),
            'data' => [
                'title' => 'Low stock alert',
                'message' => 'Tomatoes are running low.',
                'reference' => 'Tomatoes',
                'url' => '/farmer/products',
                'icon' => 'bi-exclamation-triangle',
                'category' => 'inventory',
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $notification;
    }
}
