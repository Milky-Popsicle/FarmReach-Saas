@extends('layouts.app')

@section('title', $announcement->title . ' - Announcements - FarmReach')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="farm-card p-4 p-md-5">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="rounded-circle bg-farm-primary text-warning p-2 d-inline-flex">
                        <i class="bi bi-megaphone-fill fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-bold mb-0 text-farm-primary">{{ $announcement->title }}</h3>
                        <small class="text-muted">Published by {{ $announcement->author->name ?? 'Administrator' }}</small>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 my-4 border">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">When / Date:</span>
                            <strong class="text-dark"><i class="bi bi-calendar3 me-1 text-success"></i> {{ $announcement->event_date ?? 'To be announced' }}</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">Where / Location:</span>
                            <strong class="text-dark"><i class="bi bi-geo-alt-fill me-1 text-danger"></i> {{ $announcement->location ?? 'Surigao City' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-farm-primary text-uppercase small">About this Announcement:</h6>
                    <p class="text-secondary leading-relaxed" style="font-size: 1.05rem; white-space: pre-line;">
                        {{ $announcement->description }}
                    </p>
                </div>

                <div class="pt-3 border-top d-flex justify-content-between">
                    <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary">
                        &larr; Back to Announcements
                    </a>
                    <a href="{{ route('marketplace') }}" class="btn btn-farm-primary">
                        Go to Marketplace
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
