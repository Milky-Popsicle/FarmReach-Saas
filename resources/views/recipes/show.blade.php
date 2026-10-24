@extends('layouts.app')

@section('title', $recipe->title . ' - Recipe Matching - FarmReach')

@section('content')
<div class="container py-4">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('recipes.index') }}" class="text-decoration-none">Recipes</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $recipe->title }}</li>
        </ol>
    </nav>

    <div class="row g-4 mb-4">
        <!-- Recipe Overview & Instructions -->
        <div class="col-lg-5">
            <div class="farm-card p-3 mb-4">
                <img src="{{ $recipe->image_path }}" class="img-fluid rounded-3 mb-3" alt="{{ $recipe->title }}" style="max-height: 280px; width: 100%; object-fit: cover;">
                <h3 class="fw-bold text-farm-primary mb-2">{{ $recipe->title }}</h3>
                <div class="d-flex gap-3 text-muted small mb-3">
                    <span><i class="bi bi-clock-history text-warning"></i> Prep Time: <strong>{{ $recipe->prep_time }}</strong></span>
                    <span><i class="bi bi-egg-fried text-success"></i> Ingredients: <strong>{{ count($matchedIngredients) }}</strong></span>
                </div>
                <p class="text-secondary small mb-3">{{ $recipe->description }}</p>

                <hr>

                <h6 class="fw-bold text-farm-primary mb-2"><i class="bi bi-journal-text me-1"></i> Cooking Instructions:</h6>
                <div class="bg-light p-3 rounded-3 small text-muted font-monospace" style="white-space: pre-line; line-height: 1.6;">
                    {{ $recipe->instructions ?? 'Step-by-step instructions available during preparation.' }}
                </div>
            </div>
        </div>

        <!-- Ingredient-to-Product Matching Panel (Key Capstone Feature) -->
        <div class="col-lg-7">
            <div class="farm-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="fw-bold text-farm-primary mb-0">
                            <i class="bi bi-link-45deg text-warning me-1"></i> Ingredient-to-Marketplace Matcher
                        </h4>
                        <p class="text-muted small mb-0">Matching recipe requirements directly with local farm inventories</p>
                    </div>
                    <div>
                        <span class="badge bg-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>{{ $availableCount }} In Stock</span>
                        @if($unavailableCount > 0)
                            <span class="badge bg-secondary px-2 py-1"><i class="bi bi-dash-circle me-1"></i>{{ $unavailableCount }} Unavailable</span>
                        @endif
                    </div>
                </div>

                <form id="recipeBatchCartForm" action="{{ route('recipes.add-batch') }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        @foreach($matchedIngredients as $index => $item)
                            @php
                                $ingredient = $item['ingredient'];
                                $isAvailable = $item['is_available'];
                                $products = $item['products'];
                            @endphp

                            <div class="ingredient-row {{ $isAvailable ? 'ingredient-available' : 'ingredient-unavailable' }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="fw-bold mb-1 text-dark">
                                            {{ $ingredient->name }}
                                            @if($ingredient->quantity_description)
                                                <span class="text-muted fw-normal small">({{ $ingredient->quantity_description }})</span>
                                            @endif
                                        </h6>
                                        @if($ingredient->category)
                                            <span class="badge bg-light text-muted border small" style="font-size: 10px;">{{ $ingredient->category->name }}</span>
                                        @endif
                                    </div>

                                    <div>
                                        @if($isAvailable)
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">
                                                <i class="bi bi-check2"></i> Available in Local Farms
                                            </span>
                                        @else
                                            <!-- Explicit Requirement: Clearly indicate that it is unavailable rather than displaying unrelated products -->
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 small">
                                                <i class="bi bi-x-circle"></i> Unavailable in Local Farms
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if($isAvailable)
                                    <!-- Matching products list from farmers -->
                                    <div class="mt-2 pt-2 border-top">
                                        @foreach($products as $prod)
                                            <div class="d-flex align-items-center justify-content-between p-2 rounded bg-white border mb-1">
                                                <div class="form-check d-flex align-items-center mb-0">
                                                    <input class="form-check-input recipe-ingredient-check me-2" 
                                                           type="checkbox" 
                                                           name="products[{{ $prod->id }}][id]" 
                                                           value="{{ $prod->id }}" 
                                                           id="prod_{{ $prod->id }}" 
                                                           data-price="{{ $prod->price }}" 
                                                           checked>
                                                    <input type="hidden" name="products[{{ $prod->id }}][quantity]" value="1">
                                                    <label class="form-check-label small" for="prod_{{ $prod->id }}">
                                                        <strong class="text-dark">{{ $prod->name }}</strong>
                                                        <span class="text-muted">&bull; {{ $prod->farmer?->name ?? 'Local Farmer' }}</span>
                                                        <span class="text-muted d-block d-md-inline" style="font-size: 11px;">
                                                            <i class="bi bi-geo-alt-fill text-danger"></i> {{ $prod->farmer?->address ?? 'Local Farm' }}
                                                        </span>
                                                    </label>
                                                </div>

                                                <div class="text-end">
                                                    <strong class="text-farm-primary small">₱{{ number_format($prod->price, 2) }}</strong>
                                                    <span class="text-muted small d-block" style="font-size: 10px;">{{ $prod->quantity }} left</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="mt-1 small text-muted fst-italic" style="font-size: 11px;">
                                        No active local farmers have listed stock for this ingredient today. Pantry staples (e.g. vinegar, soy sauce, specialized spices) can be sourced separately.
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <!-- Batch Add to Cart Summary Bar -->
                    <div class="p-3 rounded-3 bg-light border d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                        <div>
                            <span class="text-muted small">Selected Ingredients:</span>
                            <strong id="selectedIngredientCount" class="fs-5 text-dark ms-1">{{ $availableCount }}</strong>
                            <div class="small text-muted">
                                Estimated Total: <strong id="estimatedIngredientsTotal" class="text-farm-primary fs-5 ms-1">₱0.00</strong>
                            </div>
                        </div>

                        <div class="d-flex gap-2 w-100 w-md-auto">
                            <button type="submit" id="btnBatchAddCart" class="btn btn-farm-primary fw-bold flex-grow-1 px-4 py-2" {{ $availableCount === 0 ? 'disabled' : '' }}>
                                <i class="bi bi-bag-plus-fill me-2"></i> Add Selected to Cart
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function () {
        // Trigger initial calculation
        if (typeof updateRecipeSelection === 'function') {
            updateRecipeSelection();
        } else {
            let total = 0;
            let count = 0;
            $('.recipe-ingredient-check:checked').each(function () {
                count++;
                total += parseFloat($(this).data('price')) || 0;
            });
            $('#selectedIngredientCount').text(count);
            $('#estimatedIngredientsTotal').text('₱' + total.toFixed(2));
        }

        $('.recipe-ingredient-check').on('change', function () {
            $(this).closest('.form-check')
                .find('input[name$="[quantity]"]')
                .prop('disabled', !this.checked);

            let total = 0;
            let count = 0;
            $('.recipe-ingredient-check:checked').each(function () {
                count++;
                total += parseFloat($(this).data('price')) || 0;
            });
            $('#selectedIngredientCount').text(count);
            $('#estimatedIngredientsTotal').text('₱' + total.toFixed(2));
            $('#btnBatchAddCart').prop('disabled', count === 0);
        });
    });
</script>
@endpush
@endsection
