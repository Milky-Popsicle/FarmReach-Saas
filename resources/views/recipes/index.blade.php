@extends('layouts.app')

@section('title', 'Recipe-Based Shopping - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <!-- Header Banner -->
    <div class="farm-card p-4 p-md-5 mb-4 bg-light border-0" style="background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%); color: white;">
        <div class="row align-items-center">
            <div class="col-md-8">
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-2">
                    <i class="bi bi-magic me-1"></i> Smart Meal Prep
                </span>
                <h1 class="display-6 fw-bold text-white mb-2">Recipe-to-Product Shopping</h1>
                <p class="lead text-light mb-0" style="font-size: 1.1rem;">
                    Select your favorite dish, and FarmReach Cloud will automatically match all required agricultural ingredients with fresh produce from nearby farmers.
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="{{ route('marketplace') }}" class="btn btn-outline-light">
                    <i class="bi bi-shop me-1"></i> Full Marketplace
                </a>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="farm-card p-3 mb-4">
        <form action="{{ route('recipes.index') }}" method="GET" class="row g-2">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search recipe (e.g. Adobo, Pinakbet, Sinigang, Chopsuey) or ingredient..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-farm-primary flex-grow-1">Search</button>
                @if(request('search'))
                    <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                @endif
            </div>
        </form>
    </div>

    <!-- Recipe Grid -->
    <div class="row g-4">
        @forelse($recipes as $recipe)
            <div class="col-md-6 col-lg-3">
                <div class="farm-card h-100 farm-card-interactive d-flex flex-column" data-url="{{ route('recipes.show', $recipe) }}">
                    <img src="{{ $recipe->image_path }}" class="product-card-img" alt="{{ $recipe->title }}" style="height: 180px;">
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-farm-primary small">{{ $recipe->ingredients->count() }} Ingredients</span>
                            <span class="text-muted small"><i class="bi bi-clock"></i> {{ $recipe->prep_time }}</span>
                        </div>

                        <h5 class="fw-bold mb-2 text-farm-primary">{{ $recipe->title }}</h5>
                        <p class="text-muted small mb-3" style="height: 42px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                            {{ $recipe->description }}
                        </p>

                        <div class="mt-auto pt-3 border-top">
                            <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-farm-primary w-100 fw-bold">
                                <i class="bi bi-basket2 me-1"></i> Match Ingredients &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <p class="text-muted">No recipes found matching your criteria.</p>
                <a href="{{ route('recipes.index') }}" class="btn btn-farm-outline">Show All Recipes</a>
            </div>
        @endforelse
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $recipes->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
