@extends('layouts.admin')

@section('title', 'Manage Announcements - Admin Panel')
@section('header_title', 'Admin Announcements')

@section('content')
<div class="admin-announcements-view">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-1">
                Admin View
            </span>
            <h2 class="fw-bold mb-0 text-farm-primary">Announcements Management</h2>
            <p class="text-muted small mb-0">Publish notices, agricultural training schedules, and festival updates</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-farm-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Announcement Form (Exact match to Wireframe page 26) -->
    <div class="farm-card p-4 mb-4">
        <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">
            <i class="bi bi-pencil-square text-warning me-1"></i> Post New Announcement
        </h5>

        <form action="{{ route('admin.announcements.store') }}" method="POST">
            @csrf

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="title" class="form-label fw-bold small">Title</label>
                    <input type="text" class="form-control" id="title" name="title" placeholder="e.g. Harvest Festival or Organic Seminar" required>
                </div>
                <div class="col-md-4">
                    <label for="location" class="form-label fw-bold small">Where (Location)</label>
                    <input type="text" class="form-control" id="location" name="location" placeholder="e.g. Surigao City Plaza or CCIS Hall" required>
                </div>
                <div class="col-md-4">
                    <label for="event_date" class="form-label fw-bold small">When (Date / Schedule)</label>
                    <input type="text" class="form-control" id="event_date" name="event_date" placeholder="e.g. October 15, 2026 or Next Monday" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-bold small">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Write announcement details, participating farmers, guidelines..." required></textarea>
            </div>

            <button type="submit" class="btn btn-farm-primary fw-bold px-4">
                <i class="bi bi-plus-lg me-1"></i> + Add Announcement
            </button>
        </form>
    </div>

    <!-- Recent Announcements List (Exact match to Wireframe page 26) -->
    <div class="farm-card p-4">
        <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">
            <i class="bi bi-list-task text-warning me-1"></i> Recent Announcements
        </h5>

        <div class="d-flex flex-column gap-2">
            @forelse($announcements as $ann)
                <div class="p-3 border rounded-3 bg-light d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">{{ $ann->title }} &mdash; <span class="text-muted fw-normal small">{{ $ann->event_date }}</span></h6>
                        <small class="text-muted d-block"><i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ $ann->location }}</small>
                        <p class="small text-secondary mb-0 mt-1" style="max-width: 650px;">{{ $ann->description }}</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('announcements.show', $ann) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-eye"></i> View
                        </a>
                        <button type="button" 
                                class="btn btn-sm btn-outline-primary btn-edit-announcement"
                                data-bs-toggle="modal" 
                                data-bs-target="#editAnnouncementModal"
                                data-id="{{ $ann->id }}"
                                data-title="{{ $ann->title }}"
                                data-location="{{ $ann->location }}"
                                data-event-date="{{ $ann->event_date }}"
                                data-description="{{ $ann->description }}">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                        <form action="{{ route('admin.announcements.delete', $ann) }}" method="POST" class="swal-confirm m-0" data-confirm="Are you sure you want to delete the announcement '{{ $ann->title }}'?" data-confirm-btn="Yes, delete announcement">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger px-3">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center py-4">No announcements created yet.</p>
            @endforelse
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $announcements->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<!-- Modal: Edit Announcement -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content farm-card border-0">
            <div class="modal-header modal-header-farm">
                <h5 class="modal-title fw-bold">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_announcement_title" class="form-label fw-bold small">Title</label>
                        <input type="text" class="form-control" id="edit_announcement_title" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_announcement_location" class="form-label fw-bold small">Where (Location)</label>
                        <input type="text" class="form-control" id="edit_announcement_location" name="location" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_announcement_event_date" class="form-label fw-bold small">When (Date / Schedule)</label>
                        <input type="text" class="form-control" id="edit_announcement_event_date" name="event_date" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_announcement_description" class="form-label fw-bold small">Description</label>
                        <textarea class="form-control" id="edit_announcement_description" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-farm-primary px-4 fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
