@extends('layouts.app')

@section('title', 'Real-Time Price Comparison - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1 text-farm-primary"><i class="bi bi-bar-chart-steps text-warning me-2"></i>Farmer Price Comparison Matrix</h2>
            <p class="text-muted mb-0">Compare agricultural crop prices from multiple local farmers in real time to get the best value.</p>
        </div>
        <a href="{{ route('marketplace') }}" class="btn btn-farm-outline">
            <i class="bi bi-arrow-left me-1"></i> Back to Marketplace
        </a>
    </div>

    <!-- Category selector tabs -->
    <div class="farm-card p-3 mb-4">
        <span class="small fw-bold text-muted text-uppercase d-block mb-2">Select Category to Compare:</span>
        <div class="d-flex flex-wrap gap-2">
            @foreach($categories as $cat)
                <a href="{{ route('marketplace.compare', ['category_id' => $cat->id]) }}" class="badge rounded-pill {{ $selectedCategory == $cat->id ? 'bg-farm-primary text-white' : 'bg-light text-dark border' }} text-decoration-none px-3 py-2">
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>
    </div>

    <!-- Comparison Table -->
    <div class="farm-card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Crop / Produce</th>
                        <th>Farmer & Location</th>
                        <th>Weight / Unit</th>
                        <th>Price (PHP)</th>
                        <th>Stock Availability</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comparisonProducts as $prod)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ $prod->image_path }}" class="rounded-3" alt="{{ $prod->name }}" style="width: 48px; height: 48px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $prod->name }}</div>
                                        <small class="text-muted">{{ $prod->category?->name }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $prod->farmer?->name ?? 'Local Farmer' }}</div>
                                <div class="text-muted small"><i class="bi bi-geo-alt-fill text-danger"></i> {{ $prod->farmer?->address ?? 'Surigao del Norte' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $prod->kilograms }} {{ $prod->unit }}</span>
                            </td>
                            <td>
                                <span class="fs-5 fw-bold text-farm-primary">₱{{ number_format($prod->price, 2) }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $prod->quantity > 10 ? 'bg-success' : ($prod->quantity > 0 ? 'bg-warning text-dark' : 'bg-danger') }}">
                                    {{ $prod->stock_status }} ({{ $prod->quantity }})
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('marketplace.show', $prod) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    <form action="{{ route('cart.add') }}" method="POST" class="form-add-to-cart m-0">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $prod->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-sm btn-farm-primary">
                                            <i class="bi bi-cart-plus"></i> Buy
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                No products listed under this category yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
