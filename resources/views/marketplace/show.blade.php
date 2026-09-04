@extends('layouts.app')

@section('title', $product->name . ' - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('marketplace') }}" class="text-decoration-none">Marketplace</a></li>
            <li class="breadcrumb-item"><a href="{{ route('marketplace', ['category' => $product->category_id]) }}" class="text-decoration-none">{{ $product->category?->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Product Photo & Main Info -->
        <div class="col-lg-6">
            <div class="farm-card p-3 mb-4 text-center">
                <img src="{{ $product->image_path }}" class="img-fluid rounded-3 shadow-sm" alt="{{ $product->name }}" style="max-height: 420px; width: 100%; object-fit: cover;">
            </div>

            <!-- Farmer Profile Card -->
            <div class="farm-card p-4">
                <h5 class="fw-bold mb-3 text-farm-primary"><i class="bi bi-person-badge-fill me-2"></i>Produced by Local Farmer</h5>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-farm-primary text-warning d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 56px; height: 56px;">
                        {{ substr($product->farmer->name, 0, 1) }}
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">{{ $product->farmer->name }}</h5>
                        <div class="text-muted small">
                            <i class="bi bi-geo-alt-fill text-danger"></i> {{ $product->farmer->address ?? 'Surigao del Norte' }}
                        </div>
                    </div>
                </div>

                @if($product->farmer->bio)
                    <p class="text-muted small fst-italic mb-3">
                        "{{ $product->farmer->bio }}"
                    </p>
                @endif

                <div class="row g-2 pt-2 border-top">
                    <div class="col-6">
                        <span class="text-muted small d-block">Direct Contact</span>
                        <strong class="small">{{ $product->farmer->phone_number ?? 'Inquiry via app' }}</strong>
                    </div>
                    <div class="col-6 text-end">
                        <a href="{{ route('marketplace', ['farmer_id' => $product->farmer->id]) }}" class="btn btn-sm btn-farm-outline">
                            View Farmer's Farm Stand
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Purchase Actions & Price Comparison -->
        <div class="col-lg-6">
            <div class="farm-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-farm-primary text-white text-uppercase px-3 py-1">
                        {{ $product->category?->name ?? 'Fresh Produce' }}
                    </span>
                    <span class="badge {{ $product->quantity > 10 ? 'bg-success' : 'bg-warning text-dark' }} px-3 py-1">
                        {{ $product->stock_status }} ({{ $product->quantity }} available)
                    </span>
                </div>

                <h2 class="fw-bold text-farm-primary mb-2">{{ $product->name }}</h2>

                <div class="mb-3 d-flex align-items-baseline gap-2">
                    <span class="display-6 fw-bold text-farm-primary">₱{{ number_format($product->price, 2) }}</span>
                    <span class="text-muted fs-5">per {{ $product->kilograms }} {{ $product->unit }}</span>
                </div>

                <p class="text-secondary mb-4 leading-relaxed">
                    {{ $product->description }}
                </p>

                <hr class="my-4">

                <!-- Order / Add to Cart Form -->
                <form action="{{ route('cart.add') }}" method="POST" class="form-add-to-cart">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Quantity to Purchase</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="qty-control d-flex align-items-center">
                                <button type="button" class="qty-btn btn-qty-minus">-</button>
                                <input type="number" name="quantity" class="qty-input" value="1" min="1" max="{{ $product->quantity }}" style="width: 80px; height: 38px; font-size: 16px;">
                                <button type="button" class="qty-btn btn-qty-plus">+</button>
                            </div>
                            <span class="text-muted small">
                                Total estimated weight: <strong id="dynamicWeight">{{ $product->kilograms }}</strong> kg
                            </span>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3">
                        <button type="submit" class="btn btn-farm-primary flex-grow-1 py-2 fw-bold">
                            <i class="bi bi-cart-plus me-2"></i> Add to Cart
                        </button>
                        <a href="{{ route('recipes.index') }}" class="btn btn-farm-outline py-2 fw-bold">
                            <i class="bi bi-book me-1"></i> Recipes using this
                        </a>
                    </div>

                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        <strong>Direct Trade Guarantee:</strong> Available for both standard household orders and restaurant bulk wholesale quantities with local pick-up or direct delivery.
                    </div>
                </form>
            </div>

            <!-- Real-Time Price Comparison across Multiple Farmers (Scope requirement 1.4) -->
            @if($priceComparisons->isNotEmpty())
                <div class="farm-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-farm-primary">
                            <i class="bi bi-arrow-left-right text-warning me-2"></i>Price Comparison (Other Local Farmers)
                        </h5>
                        <a href="{{ route('marketplace.compare') }}" class="small text-decoration-none text-muted">Full Matrix &rarr;</a>
                    </div>
                    <p class="text-muted small mb-3">Compare real-time prices for related crops in the same category:</p>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Farmer & Location</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($priceComparisons as $comp)
                                    <tr>
                                        <td class="fw-bold text-dark">{{ $comp->name }}</td>
                                        <td>
                                            <div>{{ $comp->farmer->name }}</div>
                                            <div class="text-muted" style="font-size: 11px;"><i class="bi bi-geo-alt"></i> {{ $comp->farmer->address }}</div>
                                        </td>
                                        <td class="fw-bold text-success">₱{{ number_format($comp->price, 2) }}</td>
                                        <td>
                                            <a href="{{ route('marketplace.show', $comp) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
