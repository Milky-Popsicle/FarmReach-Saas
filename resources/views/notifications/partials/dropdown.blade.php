<div class="notification-dropdown-header">
    <div>
        <span class="fw-bold d-block">Notifications</span>
        <span class="text-muted small" data-notification-unread-label>
            @if($unreadNotificationsCount > 0)
                {{ $unreadNotificationsCount }} unread
            @else
                You're all caught up
            @endif
        </span>
    </div>
    <form action="{{ route('notifications.read-all') }}" method="POST" class="m-0{{ $unreadNotificationsCount > 0 ? '' : ' d-none' }}" data-notification-mark-all>
        @csrf
        <button type="submit" class="btn btn-sm btn-link notification-link-button">Mark all read</button>
    </form>
</div>

<div class="notification-dropdown-body" data-notification-list>
    @forelse($latestNotifications as $notification)
        @php($data = $notification->data)
        <form action="{{ route('notifications.read', $notification) }}" method="POST" class="m-0" data-notification-read-form data-notification-id="{{ $notification->id }}">
            @csrf
            @if(! empty($data['url']))
                <input type="hidden" name="redirect_to" value="{{ $data['url'] }}">
            @endif
            <button type="submit" class="notification-dropdown-item {{ $notification->read_at ? '' : 'is-unread' }}">
                <span class="notification-item-icon"><i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i></span>
                <span class="notification-item-content">
                    <span class="notification-item-title">{{ $data['title'] ?? 'Notification' }}</span>
                    <span class="notification-item-message">{{ $data['message'] ?? '' }}</span>
                    <span class="notification-item-meta">
                        {{ $notification->created_at->diffForHumans() }}
                        @if(! empty($data['reference']))
                            &bull; {{ $data['reference'] }}
                        @endif
                    </span>
                </span>
                @if($notification->read_at === null)
                    <span class="notification-unread-dot" data-notification-unread-dot aria-label="Unread"></span>
                @endif
            </button>
        </form>
    @empty
        <div class="notification-empty" data-notification-empty>
            <i class="bi bi-bell-slash"></i>
            <span class="fw-semibold d-block">No notifications yet</span>
            <span class="small">Order updates and stock alerts will appear here.</span>
        </div>
    @endforelse
</div>

<div class="notification-dropdown-footer">
    <a href="{{ route('notifications.index') }}">View all notifications</a>
</div>
