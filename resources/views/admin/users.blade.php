@extends('layouts.admin')

@section('title', 'Manage Users - Admin Panel')
@section('header_title', 'Admin Manage Users')

@section('content')
<div class="admin-users-view">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-1">
                Admin View
            </span>
            <h2 class="fw-bold mb-0 text-farm-primary">Manage Users</h2>
            <p class="text-muted small mb-0">View registered farmers and buyers, review locations, and manage platform access</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-farm-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Return to Dashboard
        </a>
    </div>

    <div class="farm-card p-4">
        <!-- Search and Role filter -->
        <form action="{{ route('admin.users') }}" method="GET" class="row g-2 mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, email, or phone number..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="">All Roles (Farmers & Buyers)</option>
                    <option value="farmer" {{ request('role') === 'farmer' ? 'selected' : '' }}>Farmers Only</option>
                    <option value="buyer" {{ request('role') === 'buyer' ? 'selected' : '' }}>Buyers Only</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-farm-primary w-100">Filter</button>
            </div>
        </form>

        <!-- Manage Users Table (Strict match to Wireframe page 25) -->
        <div class="table-responsive">
            <table class="table table-hover align-middle farm-table mb-0">
                <thead>
                    <tr>
                        <th>NAME</th>
                        <th>CONTACT & ADDRESS</th>
                        <th>ROLE</th>
                        <th>REGISTERED</th>
                        <th class="text-end">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-bold text-dark" style="width: 36px; height: 36px; font-size: 13px;">
                                        {{ substr($u->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $u->name }}</div>
                                        <small class="text-muted">{{ $u->email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small">{{ $u->phone_number ?? 'No phone' }}</div>
                                <div class="text-muted small text-truncate" style="max-width: 220px;" title="{{ $u->address }}">
                                    <i class="bi bi-geo-alt text-danger"></i> {{ $u->address ?? 'Location not specified' }}
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $u->isFarmer() ? 'bg-success' : 'bg-primary' }} text-capitalize px-3 py-1">
                                    {{ $u->role }}
                                </span>
                            </td>
                            <td class="small text-muted">
                                {{ $u->created_at->format('M d, Y') }}
                            </td>
                            <td class="text-end">
                                <form action="{{ route('admin.users.delete', $u) }}" method="POST" class="swal-confirm m-0" data-confirm="Are you sure you want to permanently delete the {{ $u->role }} account for '{{ $u->name }}'?" data-confirm-btn="Yes, delete user">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm px-3 fw-bold">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                No users found matching your search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $users->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
