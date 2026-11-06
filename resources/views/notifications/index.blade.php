@extends('layouts.app')

@section('title', 'Notifications - FarmReach Cloud')

@section('content')
    <div class="container py-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">
                    <i class="bi bi-bell me-2 text-farm-primary"></i>Notifications
                </h1>
                <p class="text-muted mb-0" data-notification-history-summary>
                    @if($unreadNotificationsCount > 0)
                        You have {{ $unreadNotificationsCount }} unread notification{{ $unreadNotificationsCount === 1 ? '' : 's' }}.
                    @else
                        You're all caught up.
                    @endif
                </p>
            </div>
            <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0{{ $unreadNotificationsCount > 0 ? '' : ' d-none' }}" data-notification-mark-all>
                @csrf
                <button type="submit" class="btn btn-farm-primary">
                    <i class="bi bi-check2-all me-1"></i> Mark all as read
                </button>
            </form>
        </div>

        @if($notifications->isEmpty())
            <div class="farm-card p-5 text-center" data-notification-history-empty>
                <div class="notification-empty-icon mx-auto mb-3">
                    <i class="bi bi-bell-slash"></i>
                </div>
                <h2 class="h5 fw-bold mb-2">No notifications yet</h2>
                <p class="text-muted mb-4">Order updates and low-stock alerts will appear here.</p>
                <a href="{{ route('marketplace') }}" class="btn btn-farm-accent text-dark fw-bold">Browse the marketplace</a>
            </div>
        @endif

        <div class="notification-list{{ $notifications->isEmpty() ? ' d-none' : '' }}" data-notification-history-list>
            @foreach($notifications as $notification)
                @php($data = $notification->data)
                <article class="notification-list-item {{ $notification->read_at ? '' : 'is-unread' }}" data-notification-id="{{ $notification->id }}" data-notification-history-item>
                    <span class="notification-list-icon">
                        <i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i>
                    </span>
                    <div class="notification-list-content">
                        <div class="d-flex flex-wrap justify-content-between gap-1">
                            <h2 class="h6 fw-bold mb-1">{{ $data['title'] ?? 'Notification' }}</h2>
                            <time class="notification-time" datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->format('M j, Y g:i A') }}">
                                {{ $notification->created_at->diffForHumans() }}
                            </time>
                        </div>
                        <p class="text-muted mb-2">{{ $data['message'] ?? '' }}</p>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            @if(! empty($data['reference']))
                                <span class="badge bg-light text-dark border">{{ $data['reference'] }}</span>
                            @endif
                            <span class="notification-time">{{ $notification->created_at->format('M j, Y g:i A') }}</span>
                            <span class="badge bg-success-subtle text-success-emphasis{{ $notification->read_at ? '' : ' d-none' }}" data-notification-read-badge>Read</span>
                        </div>
                    </div>
                    <div class="notification-list-actions">
                        @if(! empty($data['url']))
                            <form action="{{ route('notifications.read', $notification) }}" method="POST" class="m-0" data-notification-read-form data-notification-id="{{ $notification->id }}">
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ $data['url'] }}">
                                <button type="submit" class="btn btn-sm btn-farm-primary">View</button>
                            </form>
                        @endif
                        @if($notification->read_at === null)
                            <form action="{{ route('notifications.read', $notification) }}" method="POST" class="m-0" data-notification-read-form data-notification-id="{{ $notification->id }}" data-notification-mark-read>
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark as read</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($notifications->isNotEmpty())
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
