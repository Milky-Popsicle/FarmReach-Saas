<?php

namespace App\Http\Controllers;

use App\Notifications\NotificationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->latest()->paginate(20),
            'unreadNotificationsCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function feed(): JsonResponse
    {
        $user = Auth::user();

        return response()
            ->json([
                'unread_count' => $user->unreadNotifications()->count(),
                'notifications' => $user->notifications()
                    ->latest()
                    ->take(20)
                    ->get()
                    ->map(fn ($notification): array => NotificationPayload::for($notification))
                    ->values(),
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $userNotification = Auth::user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $userNotification->markAsRead();

        $redirectTo = NotificationPayload::safeUrl($request->input('redirect_to'));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'notification' => NotificationPayload::for($userNotification),
                'unread_count' => Auth::user()->unreadNotifications()->count(),
                'redirect_url' => $redirectTo,
            ]);
        }

        if ($redirectTo !== null) {
            return redirect($redirectTo);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
