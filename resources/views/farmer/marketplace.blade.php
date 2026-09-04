@extends('layouts.app')

@section('title', 'Farmer Marketplace Hub - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <!-- Header Banner -->
    <div class="farm-card p-4 mb-4 bg-light border-0" style="background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%); color: white;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-2">
                    Farmer Portal
                </span>
                <h2 class="fw-bold text-white mb-1">Farmer Marketplace</h2>
                <p class="text-light mb-0">Overview of active market supply and buyer inquiries across FarmReach Cloud.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('farmer.products') }}" class="btn btn-farm-accent text-dark fw-bold">
                    <i class="bi bi-box-seam me-1"></i> Manage My Products
                </a>
                <a href="{{ route('farmer.orders') }}" class="btn btn-outline-light">
                    <i class="bi bi-receipt me-1"></i> Incoming Orders
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Bar (Matching wireframe page 27 top 3 cards) -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <span class="text-muted small text-uppercase fw-semibold">My Total Orders</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">{{ $farmerOrdersCount }}</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-accent">
                <span class="text-muted small text-uppercase fw-semibold">Pending Confirmation</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">{{ $farmerPendingOrders }}</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-secondary">
                <span class="text-muted small text-uppercase fw-semibold">Completed Sales</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">₱{{ number_format($farmerTotalSales, 2) }}</h3>
            </div>
        </div>
    </div>

    <!-- Featured Products Carousel (Matching wireframe page 27) -->
    <div class="farm-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-stars text-warning me-2"></i>Featured Crops in Marketplace</h4>
            <a href="{{ route('farmer.products') }}" class="btn btn-sm btn-farm-primary">Add Your Crop Listing +</a>
        </div>

        <div class="row g-3">
            @foreach($featuredProducts as $item)
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="farm-card h-100 p-2 text-center d-flex flex-column">
                        <img src="{{ $item->image_path }}" class="rounded-3 mb-2" alt="{{ $item->name }}" style="height: 110px; object-fit: cover;">
                        <h6 class="fw-bold small mb-1 text-truncate">{{ $item->name }}</h6>
                        <span class="text-muted small d-block" style="font-size: 11px;">{{ $item->kilograms }} {{ $item->unit }}</span>
                        <strong class="text-farm-primary small mb-1">₱{{ number_format($item->price, 2) }}</strong>
                        <span class="badge bg-light text-muted border small" style="font-size: 10px;">Qty: {{ $item->quantity }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- General Market Inventory with Search -->
    <div class="farm-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h4 class="fw-bold mb-0 text-farm-primary">Community Agricultural Listings</h4>
            <form action="{{ route('farmer.marketplace') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search crops..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-sm btn-farm-primary">Search</button>
            </form>
        </div>

        <div class="row g-3">
            @foreach($allProducts as $p)
                <div class="col-md-3">
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <img src="{{ $p->image_path }}" class="rounded-2" alt="{{ $p->name }}" style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <h6 class="mb-0 fw-bold small text-truncate" style="max-width: 150px;">{{ $p->name }}</h6>
                                <small class="text-muted">{{ $p->farmer->name }}</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-farm-primary">₱{{ number_format($p->price, 2) }}</span>
                            <span class="badge bg-white border text-dark">{{ $p->quantity }} in stock</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $allProducts->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
