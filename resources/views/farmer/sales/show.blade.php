@extends('layouts.app')

@section('title', 'Sale Details - Farmer Hub')

@section('content')
@php
    $itemsSubtotal = $order->items->sum(fn ($item) => (float) $item->subtotal);
    $isCompleted = in_array($order->status, \App\Models\Order::COMPLETED_STATUSES, true);
@endphp

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-farm-primary">
                <i class="bi bi-receipt-cutoff text-warning me-2"></i>Sale Details
            </h2>
            <p class="text-muted small mb-0">
                Transaction <strong>#{{ $order->order_number }}</strong>
                &bull; {{ $order->created_at->format('M d, Y h:i A') }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('farmer.sales.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Sales History
            </a>
            <a href="{{ route('farmer.orders') }}" class="btn btn-farm-primary btn-sm">
                <i class="bi bi-receipt me-1"></i> Incoming Orders
            </a>
        </div>
    </div>

    @unless($isCompleted)
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div>
                This transaction is marked <strong>{{ $order->status_label }}</strong> and is not included in completed sales totals.
            </div>
        </div>
    @endunless

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="farm-card p-4 h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Sale Reference</span>
                        <h4 class="fw-bold mb-1">#{{ $order->order_number }}</h4>
                        <small class="text-muted">{{ $order->created_at->format('F d, Y \a\t h:i A') }}</small>
                    </div>
                    <span class="badge {{ $order->status_badge_class }} text-capitalize px-3 py-2">
                        {{ $order->status_label }}
                    </span>
                </div>

                <hr>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted small text-uppercase fw-semibold d-block">Fulfillment</span>
                        <strong class="text-capitalize">{{ $order->delivery_method }}</strong>
                        <small class="text-muted d-block">{{ $order->delivery_address }}</small>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small text-uppercase fw-semibold d-block">Order Type</span>
                        <strong class="text-capitalize">{{ $order->order_type }}</strong>
                        <small class="text-muted d-block">{{ $order->isBulk() ? 'Bulk / wholesale order' : 'Standard retail order' }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="farm-card p-4 h-100">
                <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">Buyer Information</h5>
                <div class="mb-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block">Name</span>
                    <strong>{{ $order->buyer?->name ?? 'Buyer unavailable' }}</strong>
                </div>
                <div class="mb-3">
                    <span class="text-muted small text-uppercase fw-semibold d-block">Contact</span>
                    <span>{{ $order->buyer?->phone_number ?? 'Not provided' }}</span>
                </div>
                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block">Delivery Address</span>
                    <span>{{ $order->delivery_address ?: 'Not provided' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="farm-card p-4">
                <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">Products Sold</h5>

                <div class="table-responsive">
                    <table class="table table-hover align-middle farm-table mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price per Unit</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold small">{{ $item->product?->name ?? 'Product unavailable' }}</div>
                                        @if($item->product?->category)
                                            <small class="text-muted">{{ $item->product->category->name }}</small>
                                        @endif
                                    </td>
                                    <td class="small">
                                        {{ number_format($item->quantity) }} {{ $item->product?->unit ?? 'unit' }}
                                    </td>
                                    <td class="small">₱{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="small fw-semibold text-end">₱{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No product lines recorded for this transaction.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-light border small text-muted mt-4 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Prices and subtotals above are the values stored when the sale was recorded. They are not recalculated from the current product catalog.
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="farm-card p-4 mb-4">
                <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">Payment Summary</h5>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Payment method</span>
                    <strong class="text-uppercase">{{ $order->payment?->payment_method ?? 'COD' }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Payment status</span>
                    <strong class="text-capitalize">{{ $order->payment?->payment_status ?? 'Pending' }}</strong>
                </div>
                @if($order->payment?->reference_number)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Reference</span>
                        <strong class="text-break text-end">{{ $order->payment->reference_number }}</strong>
                    </div>
                @endif
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Items subtotal</span>
                    <strong>₱{{ number_format($itemsSubtotal, 2) }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Shipping fee</span>
                    <strong>₱{{ number_format($order->shipping_fee, 2) }}</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Order total</span>
                    <strong class="fs-5 text-farm-primary">₱{{ number_format($order->total_amount, 2) }}</strong>
                </div>

                <div class="alert alert-light border small text-muted mt-3 mb-0">
                    No commissions, discounts, or refunds are recorded for this transaction. The total above is not a net take-home figure.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
