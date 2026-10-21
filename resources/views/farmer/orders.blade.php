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
                                        &bull; {{ $it->quantity }} {{ $it->product?->stock_unit_label ?? 'unit' }} {{ $it->product->name ?? 'Produce' }}
                                        @if($it->product && ($it->product->unit === 'kg' || $it->product->has_weight_per_unit))
                                            <span class="text-muted" style="font-size: 11px;">({{ number_format($it->product->effective_kilograms * $it->quantity, 1) }} kg)</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border small text-capitalize">
                                    {{ $ord->delivery_method }}
                                </span>
                                @if($ord->delivery_method === 'deliver')
                                    <small class="d-block text-muted" style="font-size: 10px;">Fee: ₱{{ number_format($ord->shipping_fee, 2) }}</small>
                                @endif
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
                                    {{ $ord->status_label }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    @if($ord->status === 'pending')
                                        @if($ord->delivery_method === 'deliver')
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#confirmDeliveryModalOrders{{ $ord->id }}" title="Confirm & Set Shipping">
                                                <i class="bi bi-truck me-1"></i> Confirm
                                            </button>

                                            <!-- Modal for Delivery Order Confirmation -->
                                            <div class="modal fade" id="confirmDeliveryModalOrders{{ $ord->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered modal-sm">
                                                    <div class="modal-content farm-card border-0 shadow">
                                                        <div class="modal-header bg-success text-white py-2">
                                                            <h6 class="modal-title fw-bold"><i class="bi bi-truck me-1"></i> Confirm Delivery</h6>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <div class="modal-body text-start">
                                                                <p class="small text-muted mb-2">
                                                                    Set your shipping fee for Order <strong>#{{ $ord->order_number }}</strong> based on distance to the buyer:
                                                                </p>
                                                                <label class="form-label fw-bold small">Your Delivery Rate (₱)</label>
                                                                <div class="input-group input-group-sm mb-2">
                                                                    <span class="input-group-text">₱</span>
                                                                    <input type="number" step="0.50" min="0" max="1000" name="shipping_fee" class="form-control" value="{{ $ord->shipping_fee > 0 ? $ord->shipping_fee : 60 }}" required>
                                                                </div>
                                                                <small class="text-muted" style="font-size: 11px;">Standard default is ₱60.00. You may adjust according to distance.</small>
                                                            </div>
                                                            <div class="modal-footer py-2 bg-light">
                                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-sm btn-success fw-bold">Confirm Order</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="m-0">
                                                @csrf
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Confirm Order">
                                                    <i class="bi bi-check-circle me-1"></i> Confirm
                                                </button>
                                            </form>
                                        @endif

                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="swal-confirm m-0" data-confirm="Cancel order #{{ $ord->order_number }}? The reserved stock will be returned to your inventory." data-confirm-btn="Yes, cancel">
                                            @csrf
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Order">
                                                <i class="bi bi-x-circle me-1"></i> Cancel
                                            </button>
                                        </form>
                                    @elseif($ord->status === 'confirmed')
                                        @if($ord->delivery_method === 'pickup')
                                            <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="m-0">
                                                @csrf
                                                <input type="hidden" name="status" value="picked_up">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Picked Up">
                                                    <i class="bi bi-bag-check me-1"></i> Picked Up
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="m-0">
                                                @csrf
                                                <input type="hidden" name="status" value="delivered">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Delivered">
                                                    <i class="bi bi-truck me-1"></i> Deliver
                                                </button>
                                            </form>
                                        @endif

                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="swal-confirm m-0" data-confirm="Cancel order #{{ $ord->order_number }}? The reserved stock will be returned to your inventory." data-confirm-btn="Yes, cancel">
                                            @csrf
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Order">
                                                <i class="bi bi-x-circle me-1"></i> Cancel
                                            </button>
                                        </form>
                                    @elseif(in_array($ord->status, ['delivered', 'picked_up'], true))
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check2-all me-1"></i>{{ $ord->status_label }}
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            Cancelled
                                        </span>
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
