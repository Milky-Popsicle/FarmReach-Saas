<?php

namespace App\Notifications;

use Illuminate\Notifications\DatabaseNotification;

final class NotificationPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function for(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'title' => is_string($data['title'] ?? null) ? $data['title'] : 'Notification',
            'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
            'reference' => is_string($data['reference'] ?? null) ? $data['reference'] : null,
            'url' => self::safeUrl($data['url'] ?? null),
            'icon' => self::safeIcon($data['icon'] ?? null),
            'category' => is_string($data['category'] ?? null) ? $data['category'] : null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
        ];
    }

    public static function safeUrl(mixed $url): ?string
    {
        if (
            ! is_string($url)
            || $url === ''
            || ! str_starts_with($url, '/')
            || str_starts_with($url, '//')
            || str_contains($url, '\\')
            || str_contains($url, "\r")
            || str_contains($url, "\n")
        ) {
            return null;
        }

        return $url;
    }

    private static function safeIcon(mixed $icon): string
    {
        if (is_string($icon) && preg_match('/^bi-[a-z0-9-]+$/', $icon) === 1) {
            return $icon;
        }

        return 'bi-bell';
    }
}
