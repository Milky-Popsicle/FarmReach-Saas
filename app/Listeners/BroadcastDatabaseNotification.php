<?php

namespace App\Listeners;

use App\Events\NotificationCreated;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if (
            $event->channel !== 'database'
            || ! $event->notifiable instanceof User
            || ! $event->response instanceof DatabaseNotification
        ) {
            return;
        }

        DB::afterCommit(function () use ($event): void {
            try {
                event(new NotificationCreated($event->notifiable, $event->response));
            } catch (Throwable $exception) {
                Log::warning('Unable to broadcast notification.', [
                    'notification_id' => $event->response->getKey(),
                    'user_id' => $event->notifiable->getKey(),
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }
}
