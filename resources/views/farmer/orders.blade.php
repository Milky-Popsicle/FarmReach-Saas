@extends('layouts.app')

@section('title', 'Incoming Order Management - Farmer Hub')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-receipt text-warning me-2"></i>Farmer Order Management</h2>
            <p class="text-muted small mb-0">Track and fulfill standard consumer and commercial bulk wholesale orders</p>
        </div>
        <a href="{{ route('farmer.products') }}" class="btn btn-farm-primary btn-sm">
            <i class="bi bi-boxes me-1"></i> Manage Products
        </a>
    </div>

    <!-- Quick Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <span class="text-muted small text-uppercase fw-semibold">Pending Confirmation</span>
                <h3 class="fw-bold mb-0 text-warning mt-1">{{ $pendingOrdersCount }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-accent">
                <span class="text-muted small text-uppercase fw-semibold">Bulk / Wholesale</span>
                <h3 class="fw-bold mb-0 text-dark mt-1">{{ $bulkOrdersCount }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <span class="text-muted small text-uppercase fw-semibold">Standard Orders</span>
                <h3 class="fw-bold mb-0 text-dark mt-1">{{ $standardOrdersCount }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-secondary">
                <span class="text-muted small text-uppercase fw-semibold">Delivered Revenue</span>
                <h3 class="fw-bold mb-0 text-success mt-1">₱{{ number_format($totalSales, 2) }}</h3>
            </div>
        </div>
    </div>

    <!-- Orders Filter Tabs -->
    <div class="farm-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex flex-wrap gap-2 nav-pills-farm">
                <a href="{{ route('farmer.orders') }}" class="nav-link {{ !request('type') && !request('status') ? 'active' : '' }}">
                    All Orders
                </a>
                <a href="{{ route('farmer.orders', ['type' => 'bulk']) }}" class="nav-link {{ request('type') == 'bulk' ? 'active' : '' }}">
                    <i class="bi bi-box-fill me-1"></i> Bulk Wholesale
                </a>
                <a href="{{ route('farmer.orders', ['type' => 'standard']) }}" class="nav-link {{ request('type') == 'standard' ? 'active' : '' }}">
                    Standard Retail
                </a>
                <a href="{{ route('farmer.orders', ['status' => 'pending']) }}" class="nav-link {{ request('status') == 'pending' ? 'active' : '' }}">
                    Pending Only
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle farm-table mb-0">
                <thead>
                    <tr>
                        <th>Order ID & Date</th>
                        <th>Buyer & Contact</th>
                        <th>Ordered Crop & Volume</th>
                        <th>Fulfillment</th>
                        <th>Total Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $ord)
                        <tr>
                            <td>
                                <strong class="text-dark small">#{{ $ord->order_number }}</strong>
                                <small class="text-muted d-block">{{ $ord->created_at->format('M d, Y h:i A') }}</small>
                                @if($ord->isBulk())
                                    <span class="badge bg-warning text-dark mt-1"><i class="bi bi-box-fill me-1"></i>Bulk Wholesale</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold small">{{ $ord->buyer->name }}</div>
                                <div class="text-muted" style="font-size: 11px;"><i class="bi bi-telephone"></i> {{ $ord->buyer->phone_number }}</div>
                                <div class="text-muted text-truncate" style="font-size: 11px; max-width: 160px;">
                                    <i class="bi bi-geo-alt"></i> {{ $ord->delivery_address }}
                                </div>
                            </td>
                            <td>
                                @foreach($ord->items as $it)
                                    <div class="small fw-semibold">
                                        &bull; {{ $it->quantity }}x {{ $it->product->name ?? 'Produce' }}
                                        <span class="text-muted" style="font-size: 11px;">({{ ($it->product?->kilograms ?? 1) * $it->quantity }} kg)</span>
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border small text-capitalize">
                                    {{ $ord->delivery_method }}
                                </span>
                            </td>
                            <td>
                                <strong class="text-farm-primary">₱{{ number_format($ord->total_amount, 2) }}</strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border small text-uppercase">
                                    {{ $ord->payment?->payment_method ?? 'COD' }}
                                </span>
                                <small class="d-block text-muted" style="font-size: 10px;">{{ ucfirst($ord->payment?->payment_status ?? 'pending') }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $ord->status_badge_class }} text-capitalize">
                                    {{ $ord->status }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($ord->status === 'pending')
                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Confirm Order">
                                                Confirm
                                            </button>
                                        </form>
                                    @elseif($ord->status === 'confirmed')
                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="m-0">
                                            @csrf
                                            <input type="hidden" name="status" value="delivered">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Delivered">
                                                Deliver
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                No orders matching this view.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $orders->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
