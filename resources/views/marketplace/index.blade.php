@extends('layouts.app')

@section('title', 'Agricultural Marketplace - FarmReach Cloud')

@section('content')
<div class="container py-3">
    <!-- Hero Banner -->
    <div class="farm-hero">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold text-uppercase mb-3">
                    <i class="bi bi-geo-alt-fill me-1"></i> Direct From Surigao Farmers
                </span>
                <h1 class="display-5 fw-bold text-white mb-3">Fresh Harvest, Fair Prices.</h1>
                <p class="lead text-light mb-4" style="max-width: 600px;">
                    Buy direct from local agricultural growers. No middlemen, lower prices for families and food businesses, and higher earnings for local farmers.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#browse-section" class="btn btn-farm-accent text-dark fw-bold px-4 py-2">
                        <i class="bi bi-cart3 me-1"></i> Browse Produce
                    </a>
                    <a href="{{ route('recipes.index') }}" class="btn btn-outline-light fw-bold px-4 py-2">
                        <i class="bi bi-book-half me-1"></i> Shop by Recipe
                    </a>
                    <a href="{{ route('marketplace.compare') }}" class="btn btn-outline-light fw-bold px-4 py-2">
                        <i class="bi bi-bar-chart-steps me-1"></i> Compare Farmer Prices
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-end">
                <div class="bg-white bg-opacity-10 p-4 rounded-4 backdrop-blur border border-white border-opacity-25 text-start">
                    <h5 class="fw-bold text-warning mb-3"><i class="bi bi-award-fill me-1"></i> Platform Highlights</h5>
                    <div class="d-flex align-items-center mb-2 text-white">
                        <i class="bi bi-check2-circle text-warning fs-5 me-2"></i>
                        <span>Zero intermediary commissions</span>
                    </div>
                    <div class="d-flex align-items-center mb-2 text-white">
                        <i class="bi bi-check2-circle text-warning fs-5 me-2"></i>
                        <span>Bulk & Wholesale discounts</span>
                    </div>
                    <div class="d-flex align-items-center mb-2 text-white">
                        <i class="bi bi-check2-circle text-warning fs-5 me-2"></i>
                        <span>Transparent farmer locations</span>
                    </div>
                    <div class="d-flex align-items-center text-white">
                        <i class="bi bi-check2-circle text-warning fs-5 me-2"></i>
                        <span>Recipe ingredient matching</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Bar (Matching wireframe top badges) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <span class="text-muted small text-uppercase fw-semibold">Active Products</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">{{ $totalProductsCount }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-secondary">
                <span class="text-muted small text-uppercase fw-semibold">Partner Farmers</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">{{ $totalFarmersCount }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-accent">
                <span class="text-muted small text-uppercase fw-semibold">Fulfillment Modes</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">Deliver & Pick Up</h3>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <span class="text-muted small text-uppercase fw-semibold">Payment Options</span>
                <h3 class="fw-bold mb-0 text-farm-primary mt-1">COD & GCash</h3>
            </div>
        </div>
    </div>

    <!-- Featured Products Carousel (Matching wireframe page 27 & 30) -->
    @if($featuredProducts->isNotEmpty())
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold mb-0 text-farm-primary">
                    <i class="bi bi-stars text-warning me-2"></i>Featured Fresh Harvest
                </h3>
                <span class="text-muted small">Hand-picked daily by local producers</span>
            </div>

            <div class="row g-3">
                @foreach($featuredProducts as $item)
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="farm-card h-100 farm-card-interactive d-flex flex-column" data-url="{{ route('marketplace.show', $item) }}">
                            <img src="{{ $item->image_path }}" class="product-card-img" alt="{{ $item->name }}" style="height: 120px;">
                            <div class="p-2 d-flex flex-column flex-grow-1">
                                <span class="badge bg-light text-success border small mb-1">{{ $item->category?->name ?? 'Produce' }}</span>
                                <h6 class="fw-bold text-truncate mb-1" title="{{ $item->name }}">
                                    <a href="{{ route('marketplace.show', $item) }}" class="text-decoration-none text-dark">{{ $item->name }}</a>
                                </h6>
                                <div class="text-muted small text-truncate mb-1">
                                    <i class="bi bi-geo-alt"></i> {{ $item->farmer?->name ?? 'Local Farmer' }}
                                </div>
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-farm-primary">₱{{ number_format($item->price, 2) }}</span>
                                    <form action="{{ route('cart.add') }}" method="POST" class="form-add-to-cart m-0">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $item->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-sm btn-farm-primary px-2 py-0" title="Add to Cart">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Main Browse Section -->
    <div id="browse-section" class="pt-2">
        <!-- Search & Filter Controls -->
        <div class="farm-card p-3 mb-4">
            <form action="{{ route('marketplace') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search crops (e.g. Sayote, Potato, Mango) or farmer..." value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }} ({{ $cat->products_count }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>Latest Listed</option>
                        <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-farm-primary flex-grow-1">Filter</button>
                    @if(request()->hasAny(['search', 'category', 'sort', 'farmer_id']))
                        <a href="{{ route('marketplace') }}" class="btn btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>

            <!-- Quick Category Pill Tags -->
            <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                <a href="{{ route('marketplace') }}" class="badge rounded-pill {{ !request('category') ? 'bg-farm-primary text-white' : 'bg-light text-dark border' }} text-decoration-none px-3 py-2">
                    All Products
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('marketplace', ['category' => $cat->id]) }}" class="badge rounded-pill {{ request('category') == $cat->id ? 'bg-farm-primary text-white' : 'bg-light text-dark border' }} text-decoration-none px-3 py-2">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Products Grid -->
        <div class="row g-4 mb-4">
            @forelse($products as $product)
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="farm-card h-100 farm-card-interactive d-flex flex-column" data-url="{{ route('marketplace.show', $product) }}">
                        <div class="position-relative">
                            <img src="{{ $product->image_path }}" class="product-card-img" alt="{{ $product->name }}">
                            <span class="position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75 text-white">
                                {{ $product->category?->name ?? 'Produce' }}
                            </span>
                            @if($product->quantity <= 10)
                                <span class="position-absolute top-0 end-0 m-2 badge bg-danger">
                                    Low Stock ({{ $product->quantity }})
                                </span>
                            @endif
                        </div>

                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <h5 class="fw-bold mb-1 text-truncate" title="{{ $product->name }}">
                                <a href="{{ route('marketplace.show', $product) }}" class="text-decoration-none text-dark">
                                    {{ $product->name }}
                                </a>
                            </h5>

                            <p class="text-muted small mb-2" style="height: 38px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                {{ $product->description }}
                            </p>

                            <!-- Farmer Location Badge (as required by specifications) -->
                            <div class="p-2 rounded bg-light border small mb-3">
                                <div class="fw-semibold text-dark text-truncate">
                                    <i class="bi bi-person-fill text-success"></i> {{ $product->farmer?->name ?? 'Local Farm' }}
                                </div>
                                <div class="text-muted text-truncate" style="font-size: 11px;">
                                    <i class="bi bi-geo-alt-fill text-danger"></i> {{ $product->farmer?->address ?? 'Surigao del Norte' }}
                                </div>
                            </div>

                            <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="price-tag">₱{{ number_format($product->price, 2) }}</span>
                                    <span class="text-muted small">/ {{ $product->kilograms }} {{ $product->unit }}</span>
                                </div>
                                <span class="badge bg-light text-muted border">
                                    {{ $product->quantity }} in stock
                                </span>
                            </div>

                            <!-- Add to Cart / Order Form -->
                            <form action="{{ route('cart.add') }}" method="POST" class="form-add-to-cart">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                
                                <div class="d-flex gap-2">
                                    <div class="qty-control d-flex align-items-center">
                                        <button type="button" class="qty-btn btn-qty-minus">-</button>
                                        <input type="number" name="quantity" class="qty-input" value="1" min="1" max="{{ $product->quantity }}">
                                        <button type="button" class="qty-btn btn-qty-plus">+</button>
                                    </div>
                                    <button type="submit" class="btn btn-farm-primary flex-grow-1 fw-bold btn-sm">
                                        <i class="bi bi-cart-plus me-1"></i> Add
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <div class="rounded-circle bg-light p-4 d-inline-flex mb-3">
                        <i class="bi bi-search fs-1 text-muted"></i>
                    </div>
                    <h4 class="fw-bold">No agricultural products found</h4>
                    <p class="text-muted">Try clearing search keywords or selecting a different category.</p>
                    <a href="{{ route('marketplace') }}" class="btn btn-farm-outline">Reset Search</a>
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center">
            {{ $products->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
