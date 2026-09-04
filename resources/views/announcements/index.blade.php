@extends('layouts.app')

@section('title', 'Community Announcements - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-megaphone-fill text-warning me-2"></i>Agri Announcements</h2>
            <p class="text-muted small mb-0">Agricultural workshops, trade fairs, harvest schedules, and government advisories</p>
        </div>
        @if(Auth::check() && Auth::user()->isAdmin())
            <a href="{{ route('admin.announcements') }}" class="btn btn-farm-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Post Announcement
            </a>
        @endif
    </div>

    <div class="row g-4">
        @forelse($announcements as $ann)
            <div class="col-md-6 col-lg-4">
                <div class="farm-card h-100 p-4 d-flex flex-column border">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-farm-primary small">
                            <i class="bi bi-calendar-event me-1"></i> {{ $ann->event_date ?? $ann->created_at->format('M d, Y') }}
                        </span>
                        @if($ann->location)
                            <span class="badge bg-light text-dark border small">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> {{ $ann->location }}
                            </span>
                        @endif
                    </div>

                    <h5 class="fw-bold text-farm-primary mb-2">{{ $ann->title }}</h5>

                    <p class="text-secondary small mb-3 flex-grow-1" style="height: 60px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                        {{ $ann->description }}
                    </p>

                    <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                        <span class="text-muted small">By: {{ $ann->author->name ?? 'Admin' }}</span>
                        <a href="{{ route('announcements.show', $ann) }}" class="btn btn-sm btn-farm-outline fw-bold">
                            Read more &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-megaphone fs-1 d-block mb-2"></i>
                No announcements posted at this time.
            </div>
        @endforelse
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $announcements->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
