@extends('layouts.app')

@section('title', 'Sales History - Farmer Hub')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-0 text-farm-primary">
                <i class="bi bi-graph-up-arrow text-warning me-2"></i>Sales History
            </h2>
            <p class="text-muted small mb-0">Review completed sales, product-level earnings, and transaction details.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('farmer.marketplace') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Farmer Hub
            </a>
            <a href="{{ route('farmer.orders') }}" class="btn btn-farm-primary btn-sm">
                <i class="bi bi-receipt me-1"></i> Incoming Orders
            </a>
        </div>
    </div>

    <!-- Sales Summary -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl">
            <div class="stat-card h-100">
                <span class="text-muted small text-uppercase fw-semibold">Gross Product Sales</span>
                <h3 class="fw-bold mb-1 text-farm-primary mt-1">₱{{ number_format($totalProductSales, 2) }}</h3>
                <small class="text-muted d-block">Order value incl. shipping: ₱{{ number_format($totalOrderValue, 2) }}</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <div class="stat-card stat-accent h-100">
                <span class="text-muted small text-uppercase fw-semibold">Completed Sales</span>
                <h3 class="fw-bold mb-1 text-dark mt-1">{{ number_format($completedTransactions) }}</h3>
                <small class="text-muted d-block">Delivered and picked-up orders</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <div class="stat-card stat-secondary h-100">
                <span class="text-muted small text-uppercase fw-semibold">Products Sold</span>
                <h3 class="fw-bold mb-1 text-dark mt-1">{{ number_format($productsSold) }}</h3>
                <small class="text-muted d-block">Units across completed sales</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <div class="stat-card h-100">
                <span class="text-muted small text-uppercase fw-semibold">Sales Today</span>
                <h3 class="fw-bold mb-1 text-farm-primary mt-1">₱{{ number_format($todaySales, 2) }}</h3>
                <small class="text-muted d-block">Completed orders created today</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl">
            <div class="stat-card h-100">
                <span class="text-muted small text-uppercase fw-semibold">Sales This Month</span>
                <h3 class="fw-bold mb-1 text-farm-primary mt-1">₱{{ number_format($monthSales, 2) }}</h3>
                <small class="text-muted d-block">Completed orders created this month</small>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small text-muted d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-info-circle mt-1"></i>
        <div>
            <strong>Gross product sales only.</strong>
            Shipping fees are shown separately in the transaction details; no commissions, discounts, or refunds are recorded in this system.
            This is not a net take-home figure. Sale dates use the order creation time because no completion timestamp is stored.
            Summary cards reflect all completed sales, regardless of the table filters below.
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="farm-card p-4 mb-4">
        <form action="{{ route('farmer.sales.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label for="salesSearch" class="form-label fw-semibold small">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input
                        type="search"
                        id="salesSearch"
                        name="search"
                        class="form-control @error('search') is-invalid @enderror"
                        value="{{ $search }}"
                        placeholder="Product name or order number"
                    >
                    @error('search')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-2">
                <label for="salesStatus" class="form-label fw-semibold small">Status</label>
                <select id="salesStatus" name="status" class="form-select @error('status') is-invalid @enderror">
                    @foreach ([
                        'completed' => 'Completed sales',
                        'delivered' => 'Delivered',
                        'picked_up' => 'Picked Up',
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'all' => 'All statuses',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-6 col-lg-2">
                <label for="salesFrom" class="form-label fw-semibold small">From date</label>
                <input
                    type="date"
                    id="salesFrom"
                    name="from"
                    class="form-control @error('from') is-invalid @enderror"
                    value="{{ $from }}"
                >
                @error('from')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-6 col-lg-2">
                <label for="salesTo" class="form-label fw-semibold small">To date</label>
                <input
                    type="date"
                    id="salesTo"
                    name="to"
                    class="form-control @error('to') is-invalid @enderror"
                    value="{{ $to }}"
                >
                @error('to')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-farm-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="{{ route('farmer.sales.index') }}" class="btn btn-outline-secondary" title="Reset filters" aria-label="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Sales Table -->
    <div class="farm-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div>
                <h5 class="fw-bold mb-0 text-farm-primary">Sales Transactions</h5>
                <small class="text-muted">Historical prices and subtotals are read from the order records, not current product prices.</small>
            </div>
            <span class="badge bg-light text-dark border">{{ number_format($sales->total()) }} transaction(s)</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle farm-table mb-0" style="min-width: 1050px;">
                <thead>
                    <tr>
                        <th>Date &amp; Sale</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price per Unit</th>
                        <th>Subtotal</th>
                        <th>Sale Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        @if($sale->items->isEmpty())
                            <tr>
                                <td>
                                    <strong class="text-dark small">#{{ $sale->order_number }}</strong>
                                    <small class="text-muted d-block">{{ $sale->created_at->format('M d, Y h:i A') }}</small>
                                </td>
                                <td colspan="4" class="text-muted small">No product lines recorded for this transaction.</td>
                                <td><strong class="text-farm-primary">₱{{ number_format($sale->total_amount, 2) }}</strong></td>
                                <td>
                                    <span class="badge {{ $sale->status_badge_class }} text-capitalize">{{ $sale->status_label }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('farmer.sales.show', $sale) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @else
                            @php $lineCount = $sale->items->count(); @endphp

                            @foreach($sale->items as $item)
                                <tr>
                                    @if($loop->first)
                                        <td rowspan="{{ $lineCount }}">
                                            <strong class="text-dark small">#{{ $sale->order_number }}</strong>
                                            <small class="text-muted d-block">{{ $sale->created_at->format('M d, Y h:i A') }}</small>
                                            @if($sale->isBulk())
                                                <span class="badge bg-warning text-dark mt-1"><i class="bi bi-box-fill me-1"></i>Bulk</span>
                                            @endif
                                        </td>
                                    @endif

                                    <td>
                                        <div class="fw-semibold small">{{ $item->product?->name ?? 'Product unavailable' }}</div>
                                    </td>
                                    <td class="small">
                                        {{ number_format($item->quantity) }} {{ $item->product?->unit ?? 'unit' }}
                                    </td>
                                    <td class="small">₱{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="small fw-semibold">₱{{ number_format($item->subtotal, 2) }}</td>

                                    @if($loop->first)
                                        <td rowspan="{{ $lineCount }}">
                                            <strong class="text-farm-primary">₱{{ number_format($sale->total_amount, 2) }}</strong>
                                        </td>
                                        <td rowspan="{{ $lineCount }}">
                                            <span class="badge {{ $sale->status_badge_class }} text-capitalize">{{ $sale->status_label }}</span>
                                        </td>
                                        <td rowspan="{{ $lineCount }}">
                                            <a href="{{ route('farmer.sales.show', $sale) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i> View
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-receipt fs-1 text-muted d-block mb-2"></i>
                                @if($hasFilters)
                                    <h5 class="fw-bold mb-1">No sales match these filters</h5>
                                    <p class="text-muted small mb-0">Try a different product name, status, or date range.</p>
                                @else
                                    <h5 class="fw-bold mb-1">No completed sales yet</h5>
                                    <p class="text-muted small mb-0">When an order is marked delivered or picked up, it will appear here.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $sales->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
