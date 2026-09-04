@extends('layouts.app')

@section('title', 'Recipe Directory Management - Admin Panel')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-1">
                Admin View
            </span>
            <h2 class="fw-bold mb-0 text-farm-primary">Recipe & Ingredient Directory</h2>
            <p class="text-muted small mb-0">Create culinary recipes and link ingredients to marketplace product categories</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-farm-outline btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Dashboard
        </a>
    </div>

    <!-- Create Recipe Form -->
    <div class="farm-card p-4 mb-4">
        <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">
            <i class="bi bi-plus-circle text-warning me-1"></i> Add New Recipe & Link Ingredients
        </h5>

        <form action="{{ route('admin.recipes.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label for="title" class="form-label fw-bold small">Recipe Title</label>
                    <input type="text" class="form-control" id="title" name="title" placeholder="e.g. Classic Beef Caldereta or Vegetable Lumpia" required>
                </div>
                <div class="col-md-4">
                    <label for="prep_time" class="form-label fw-bold small">Preparation Time</label>
                    <input type="text" class="form-control" id="prep_time" name="prep_time" placeholder="e.g. 40 mins">
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-bold small">Short Description</label>
                <textarea class="form-control" id="description" name="description" rows="2" placeholder="Brief summary of dish and taste profile..."></textarea>
            </div>

            <div class="mb-3">
                <label for="instructions" class="form-label fw-bold small">Cooking Instructions</label>
                <textarea class="form-control" id="instructions" name="instructions" rows="3" placeholder="Numbered cooking steps..."></textarea>
            </div>

            <div class="mb-4">
                <label for="image" class="form-label fw-bold small">Cover Photo (Optional)</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
            </div>

            <!-- Ingredients Repeater -->
            <div class="p-3 bg-light rounded-3 border mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-dark">Required Agricultural Ingredients</h6>
                    <button type="button" class="btn btn-sm btn-outline-success" id="btnAddIngredientRow">
                        <i class="bi bi-plus-lg me-1"></i> Add Ingredient
                    </button>
                </div>
                <small class="text-muted d-block mb-3">Map each ingredient to a marketplace category so FarmReach can automatically query farmer inventory.</small>

                <div id="ingredientRowsContainer">
                    <div class="row g-2 mb-2 ingredient-form-row">
                        <div class="col-md-5">
                            <input type="text" name="ingredients[0][name]" class="form-control form-control-sm" placeholder="Ingredient Name (e.g. Native Garlic)" required>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="ingredients[0][quantity_description]" class="form-control form-control-sm" placeholder="Qty (e.g. 4 cloves crushed)">
                        </div>
                        <div class="col-md-3">
                            <select name="ingredients[0][category_id]" class="form-select form-select-sm">
                                <option value="">No Category (Pantry/Other)</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 text-end">
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-remove-row" disabled><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-farm-primary fw-bold px-4">
                <i class="bi bi-save me-1"></i> Save Recipe & Ingredient Mappings
            </button>
        </form>
    </div>

    <!-- Existing Recipes Table -->
    <div class="farm-card p-4">
        <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">
            <i class="bi bi-book-half text-warning me-1"></i> Active Recipes in Catalog
        </h5>

        <div class="table-responsive">
            <table class="table table-hover align-middle farm-table mb-0">
                <thead>
                    <tr>
                        <th>Recipe Title</th>
                        <th>Prep Time</th>
                        <th>Mapped Ingredients</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recipes as $rec)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="{{ $rec->image_path }}" class="rounded-2" alt="{{ $rec->title }}" style="width: 48px; height: 48px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $rec->title }}</div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 320px;">{{ $rec->description }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="small">{{ $rec->prep_time }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($rec->ingredients as $ing)
                                        <span class="badge bg-light text-dark border small" style="font-size: 10px;">
                                            {{ $ing->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('recipes.show', $rec) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Test Match</a>
                                    <form action="{{ route('admin.recipes.delete', $rec) }}" method="POST" onsubmit="return confirm('Delete this recipe?')" class="m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger px-2">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No recipes found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $recipes->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function () {
        let rowIdx = 1;
        const categoriesOptions = `
            <option value="">No Category (Pantry/Other)</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        `;

        $('#btnAddIngredientRow').on('click', function () {
            const html = `
                <div class="row g-2 mb-2 ingredient-form-row">
                    <div class="col-md-5">
                        <input type="text" name="ingredients[${rowIdx}][name]" class="form-control form-control-sm" placeholder="Ingredient Name" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="ingredients[${rowIdx}][quantity_description]" class="form-control form-control-sm" placeholder="Qty">
                    </div>
                    <div class="col-md-3">
                        <select name="ingredients[${rowIdx}][category_id]" class="form-select form-select-sm">
                            ${categoriesOptions}
                        </select>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-remove-row"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            `;
            $('#ingredientRowsContainer').append(html);
            rowIdx++;
        });

        $(document).on('click', '.btn-remove-row', function () {
            $(this).closest('.ingredient-form-row').remove();
        });
    });
</script>
@endpush
@endsection
