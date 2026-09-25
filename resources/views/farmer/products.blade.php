@extends('layouts.app')

@section('title', 'My Products & Incoming Orders - Farmer Hub')

@section('content')
<div class="container py-4">
    <!-- Header Banner (Wireframe page 27) -->
    <div class="farm-card p-4 mb-4 bg-light border-0" style="background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%); color: white;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-2">
                    Farmer View
                </span>
                <h2 class="fw-bold text-white mb-1">Welcome, {{ $farmer->name }}!</h2>
                <p class="text-light mb-0">Here you can manage your crops and check incoming buyer orders.</p>
                <small class="text-white text-opacity-75 d-block mt-1">
                    <i class="bi bi-geo-alt-fill text-warning"></i> Registered Farm Location: <strong>{{ $farmer->address ?? 'Not set in profile' }}</strong>
                </small>
            </div>
            <div>
                <button type="button" class="btn btn-farm-accent text-dark fw-bold px-4 py-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus-circle-fill me-1"></i> Add Product
                </button>
            </div>
        </div>
    </div>

    <!-- My Products Section (Wireframe page 27) -->
    <div class="farm-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-boxes text-warning me-2"></i>My Listed Products</h4>
            <span class="text-muted small">{{ $myProducts->count() }} active crop listings</span>
        </div>

        <div class="row g-3">
            @forelse($myProducts as $prod)
                <div class="col-md-4">
                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <img src="{{ $prod->image_path }}" class="rounded-3" alt="{{ $prod->name }}" style="width: 60px; height: 60px; object-fit: cover;">
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ $prod->name }}</h6>
                                <span class="badge bg-white border text-muted small">{{ $prod->category?->name }}</span>
                            </div>
                        </div>

                        <div class="row g-2 small mb-3">
                            <div class="col-6">
                                <span class="text-muted">Stock:</span>
                                <strong>{{ $prod->stock_with_unit }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Price:</span>
                                <strong class="text-success">{{ $prod->formatted_price_per_unit }}</strong>
                            </div>
                            @if($prod->has_weight_per_unit)
                                <div class="col-12">
                                    <span class="text-muted">Weight per Unit:</span>
                                    <strong>{{ $prod->formatted_weight_per_unit }}</strong>
                                </div>
                            @endif
                        </div>

                        <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary btn-edit-product"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editProductModal"
                                    data-id="{{ $prod->id }}"
                                    data-name="{{ $prod->name }}"
                                    data-category-id="{{ $prod->category_id }}"
                                    data-price="{{ $prod->price }}"
                                    data-quantity="{{ $prod->quantity }}"
                                    data-kilograms="{{ $prod->kilograms ?? '' }}"
                                    data-unit="{{ $prod->unit }}"
                                    data-description="{{ $prod->description }}">
                                <i class="bi bi-pencil-square me-1"></i> Edit Product
                            </button>

                            <form action="{{ route('farmer.products.destroy', $prod) }}" method="POST" class="swal-confirm m-0" data-confirm="Are you sure you want to delete this '{{ $prod->name }}' crop listing? It will be removed from the marketplace." data-confirm-btn="Yes, delete it">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                    <i class="bi bi-trash3"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-4 text-muted">
                    <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                    You haven't listed any farm produce yet. Click "Add Product" above to post your harvest!
                </div>
            @endforelse
        </div>
    </div>

    <!-- Incoming Orders Section (Wireframe page 27 bottom table) -->
    <div class="farm-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-receipt-cutoff text-warning me-2"></i>Incoming Buyer Orders</h4>
                <p class="text-muted small mb-0">Confirm and update fulfillment status for customer purchases</p>
            </div>
            <a href="{{ route('farmer.orders') }}" class="btn btn-sm btn-farm-outline">
                View All Orders &rarr;
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle farm-table mb-0">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Buyer</th>
                        <th>Product & Qty</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incomingOrders as $ord)
                        <tr>
                            <td>
                                <strong class="small text-dark">#{{ $ord->order_number }}</strong>
                                <small class="text-muted d-block">{{ $ord->created_at->format('M d, Y') }}</small>
                            </td>
                            <td>
                                <div class="fw-bold small">{{ $ord->buyer->name }}</div>
                                <div class="text-muted" style="font-size: 11px;">
                                    <i class="bi bi-telephone"></i> {{ $ord->buyer->phone_number }}
                                </div>
                            </td>
                            <td>
                                @foreach($ord->items as $it)
                                    <div class="small">
                                        &bull; <strong>{{ $it->quantity }} {{ $it->product?->stock_unit_label ?? 'unit' }}</strong> {{ $it->product->name ?? 'Produce' }}
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @if($ord->isBulk())
                                    <span class="badge bg-warning text-dark"><i class="bi bi-box-fill me-1"></i>Bulk Wholesale</span>
                                @else
                                    <span class="badge bg-light text-muted border">Standard</span>
                                @endif
                            </td>
                            <td>
                                <strong class="text-farm-primary">₱{{ number_format($ord->total_amount, 2) }}</strong>
                                <small class="text-muted d-block" style="font-size: 10px;">
                                    {{ ucfirst($ord->delivery_method) }}
                                    @if($ord->delivery_method === 'deliver')
                                        &bull; Fee: ₱{{ number_format($ord->shipping_fee, 2) }}
                                    @endif
                                </small>
                            </td>
                            <td>
                                <span class="badge {{ $ord->status_badge_class }} text-capitalize">
                                    {{ $ord->status_label }}
                                </span>
                            </td>
                            <td>
                                @if(in_array($ord->status, ['delivered', 'picked_up'], true))
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check2-all me-1"></i>{{ $ord->status_label }}
                                    </span>
                                @elseif($ord->status === 'cancelled')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        Cancelled
                                    </span>
                                @else
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Update Status
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            @if($ord->status === 'pending')
                                                @if($ord->delivery_method === 'deliver')
                                                    <li>
                                                        <button type="button" class="dropdown-item text-primary" data-bs-toggle="modal" data-bs-target="#confirmDeliveryModal{{ $ord->id }}">
                                                            <i class="bi bi-truck me-1"></i> Confirm & Set Shipping Fee
                                                        </button>
                                                    </li>
                                                @else
                                                    <li>
                                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button type="submit" class="dropdown-item text-primary">
                                                                <i class="bi bi-check-circle me-1"></i> Confirm Order
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                            @elseif($ord->status === 'confirmed')
                                                @if($ord->delivery_method === 'pickup')
                                                    <li>
                                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="status" value="picked_up">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="bi bi-bag-check me-1"></i> Mark as Picked Up
                                                            </button>
                                                        </form>
                                                    </li>
                                                @else
                                                    <li>
                                                        <form action="{{ route('farmer.orders.status', $ord) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="status" value="delivered">
                                                            <button type="submit" class="dropdown-item text-success">
                                                                <i class="bi bi-truck me-1"></i> Mark as Delivered
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                            @endif
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('farmer.orders.status', $ord) }}" method="POST" class="swal-confirm" data-confirm="Are you sure you want to cancel or reject order #{{ $ord->order_number }}?" data-confirm-btn="Yes, reject order">
                                                    @csrf
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle me-1"></i> Cancel / Reject</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>

                                    @if($ord->status === 'pending' && $ord->delivery_method === 'deliver')
                                        <!-- Modal to set delivery shipping fee before confirming -->
                                        <div class="modal fade" id="confirmDeliveryModal{{ $ord->id }}" tabindex="-1" aria-hidden="true">
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
                                                            <small class="text-muted" style="font-size: 11px;">Standard default is ₱60.00. You may reduce it if the buyer lives nearby.</small>
                                                        </div>
                                                        <div class="modal-footer py-2 bg-light">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-success fw-bold">Confirm Order</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                No incoming orders yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Product (Wireframe page 28) -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content farm-card border-0">
            <div class="modal-header modal-header-farm">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-plus-circle me-2"></i>Add a Produce Listing</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('farmer.products.store') }}" method="POST" enctype="multipart/form-data" id="addProductForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label for="name" class="form-label fw-bold small">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Sayote, Cabbage, Carabao Mango, Dinorado Rice" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="category_id" class="form-label fw-bold small">Category <span class="text-danger">*</span></label>
                            <select class="form-select category-select" id="category_id" name="category_id" required>
                                <option value="" data-slug="">Select Category</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" data-slug="{{ $c->slug }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Category Unit Guidance Chips -->
                    <div id="addCategoryUnitSuggestions" class="p-2 px-3 rounded-3 bg-light border mb-3 small d-none">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <span class="text-muted"><i class="bi bi-lightbulb-fill text-warning me-1"></i>Suggested units for <strong class="cat-name-display text-dark">Vegetables</strong>:</span>
                            <div class="unit-chips d-flex flex-wrap gap-1"></div>
                        </div>
                        <div class="unit-examples text-muted mt-1" style="font-size: 11px;"></div>
                    </div>

                    <div class="row">
                        <!-- Stock (formerly Quantity) -->
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label fw-bold small">Stock (Available Quantity) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="quantity" name="quantity" placeholder="e.g. 25, 50, 100" min="0" required>
                                <span class="input-group-text stock-unit-display bg-light text-muted small">kg</span>
                            </div>
                            <div class="form-text text-muted" style="font-size: 11px;">Total harvest available for sale</div>
                        </div>

                        <!-- Selling Unit -->
                        <div class="col-md-6 mb-3">
                            <label for="unit" class="form-label fw-bold small">Selling Unit <span class="text-danger">*</span></label>
                            <select class="form-select select-selling-unit" id="unit" name="unit" required>
                                <option value="kg">kg (Kilograms)</option>
                                <option value="g">g (Grams)</option>
                                <option value="pcs">pcs (Pieces / Individual)</option>
                                <option value="bundle">bundle (Bundles)</option>
                                <option value="pack">pack (Packs)</option>
                                <option value="sack">sack (Sacks)</option>
                                <option value="crate">crate (Crates)</option>
                                <option value="box">box (Boxes)</option>
                            </select>
                            <div class="form-text text-muted" style="font-size: 11px;">How you measure or package this produce</div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Price -->
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label fw-bold small">Price (₱) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₱</span>
                                <input type="number" step="0.01" class="form-control" id="price" name="price" placeholder="e.g. 45.00" min="0.01" required>
                                <span class="input-group-text price-unit-display bg-light text-muted small">/ kg</span>
                            </div>
                            <div class="form-text text-muted" style="font-size: 11px;">Price per selected selling unit</div>
                        </div>

                        <!-- Weight per Unit (Optional) -->
                        <div class="col-md-6 mb-3">
                            <label for="kilograms" class="form-label fw-bold small">
                                Weight per Unit <span class="badge bg-light text-muted border fw-normal">Optional</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control input-weight-per-unit" id="kilograms" name="kilograms" placeholder="e.g. 0.20 for apple, 1.5 for cabbage" min="0.01">
                                <span class="input-group-text bg-light text-muted small">kg</span>
                            </div>
                            <div class="form-text weight-helper-text text-muted" style="font-size: 11px;">
                                Optional: only needed if sold by piece, bundle, pack, sack, crate, or box.
                            </div>
                        </div>
                    </div>

                    <!-- Live Produce Listing Preview -->
                    <div class="live-preview-box p-3 rounded-3 border bg-light mb-3">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 mb-2 text-uppercase fw-bold" style="font-size: 10px;">
                            <i class="bi bi-eye-fill me-1"></i>Live Produce Card Preview
                        </span>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold mb-0 preview-title text-dark">Fresh Produce</h6>
                                <span class="preview-category text-muted small">Select Category</span>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success preview-price fs-6">₱0.00 / kg</div>
                                <span class="badge bg-white border text-dark preview-stock">Stock: 0 kg</span>
                            </div>
                        </div>
                        <div class="preview-weight text-muted mt-2 pt-2 border-top small" style="display: none; font-size: 11px;">
                            <i class="bi bi-speedometer2 text-secondary me-1"></i>Weight per Unit: <strong class="preview-weight-val">0.00 kg</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold small">Description (Freshness, Variety, Harvest details)</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Describe the harvest freshness, variety, or special care..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label fw-bold small">Attach Image (Optional)</label>
                        <input type="file" class="form-control input-img-preview" id="image" name="image" accept="image/*" data-preview-target="#addProductImgPreview">
                        <div class="image-preview-container d-none mt-2 text-center">
                            <img id="addProductImgPreview" src="" alt="Live Preview" class="img-fluid rounded-3" style="max-height: 180px;">
                            <span class="d-block text-success small mt-1"><i class="bi bi-check-circle-fill me-1"></i>Image ready for upload</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-farm-primary px-4 fw-bold"><i class="bi bi-check-circle me-1"></i> Add Produce</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Product (Wireframe page 28) -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content farm-card border-0">
            <div class="modal-header modal-header-farm">
                <h5 class="modal-title fw-bold text-white"><i class="bi bi-pencil-square me-2"></i>Edit Produce Listing</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data" id="editProductForm">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label for="edit_name" class="form-label fw-bold small">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="edit_category_id" class="form-label fw-bold small">Category <span class="text-danger">*</span></label>
                            <select class="form-select category-select" id="edit_category_id" name="category_id" required>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" data-slug="{{ $c->slug }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Category Unit Guidance Chips -->
                    <div id="editCategoryUnitSuggestions" class="p-2 px-3 rounded-3 bg-light border mb-3 small d-none">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <span class="text-muted"><i class="bi bi-lightbulb-fill text-warning me-1"></i>Suggested units for <strong class="cat-name-display text-dark">Vegetables</strong>:</span>
                            <div class="unit-chips d-flex flex-wrap gap-1"></div>
                        </div>
                        <div class="unit-examples text-muted mt-1" style="font-size: 11px;"></div>
                    </div>

                    <div class="row">
                        <!-- Stock (formerly Quantity) -->
                        <div class="col-md-6 mb-3">
                            <label for="edit_quantity" class="form-label fw-bold small">Stock (Available Quantity) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                                <span class="input-group-text stock-unit-display bg-light text-muted small">unit</span>
                            </div>
                            <div class="form-text text-muted" style="font-size: 11px;">Total harvest available for sale</div>
                        </div>

                        <!-- Selling Unit -->
                        <div class="col-md-6 mb-3">
                            <label for="edit_unit" class="form-label fw-bold small">Selling Unit <span class="text-danger">*</span></label>
                            <select class="form-select select-selling-unit" id="edit_unit" name="unit" required>
                                <option value="kg">kg (Kilograms)</option>
                                <option value="g">g (Grams)</option>
                                <option value="pcs">pcs (Pieces / Individual)</option>
                                <option value="bundle">bundle (Bundles)</option>
                                <option value="pack">pack (Packs)</option>
                                <option value="sack">sack (Sacks)</option>
                                <option value="crate">crate (Crates)</option>
                                <option value="box">box (Boxes)</option>
                            </select>
                            <div class="form-text text-muted" style="font-size: 11px;">How you measure or package this produce</div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Price -->
                        <div class="col-md-6 mb-3">
                            <label for="edit_price" class="form-label fw-bold small">Price (₱) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₱</span>
                                <input type="number" step="0.01" class="form-control" id="edit_price" name="price" min="0.01" required>
                                <span class="input-group-text price-unit-display bg-light text-muted small">/ kg</span>
                            </div>
                            <div class="form-text text-muted" style="font-size: 11px;">Price per selected selling unit</div>
                        </div>

                        <!-- Weight per Unit (Optional) -->
                        <div class="col-md-6 mb-3">
                            <label for="edit_kilograms" class="form-label fw-bold small">
                                Weight per Unit <span class="badge bg-light text-muted border fw-normal">Optional</span>
                            </label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control input-weight-per-unit" id="edit_kilograms" name="kilograms" placeholder="e.g. 0.20, 1.50" min="0.01">
                                <span class="input-group-text bg-light text-muted small">kg</span>
                            </div>
                            <div class="form-text weight-helper-text text-muted" style="font-size: 11px;">
                                Optional: only needed if sold by piece, bundle, pack, sack, crate, or box.
                            </div>
                        </div>
                    </div>

                    <!-- Live Produce Listing Preview -->
                    <div class="live-preview-box p-3 rounded-3 border bg-light mb-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 mb-2 text-uppercase fw-bold" style="font-size: 10px;">
                            <i class="bi bi-eye-fill me-1"></i>Updated Produce Card Preview
                        </span>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold mb-0 preview-title text-dark">Fresh Produce</h6>
                                <span class="preview-category text-muted small">Category</span>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success preview-price fs-6">₱0.00 / kg</div>
                                <span class="badge bg-white border text-dark preview-stock">Stock: 0 kg</span>
                            </div>
                        </div>
                        <div class="preview-weight text-muted mt-2 pt-2 border-top small" style="display: none; font-size: 11px;">
                            <i class="bi bi-speedometer2 text-secondary me-1"></i>Weight per Unit: <strong class="preview-weight-val">0.00 kg</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label fw-bold small">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_image" class="form-label fw-bold small">Attach New Image (Optional)</label>
                        <input type="file" class="form-control input-img-preview" id="edit_image" name="image" accept="image/*" data-preview-target="#editProductImgPreview">
                        <div class="image-preview-container d-none mt-2 text-center">
                            <img id="editProductImgPreview" src="" alt="Live Preview" class="img-fluid rounded-3" style="max-height: 180px;">
                            <span class="d-block text-success small mt-1"><i class="bi bi-check-circle-fill me-1"></i>New image ready for upload</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-farm-primary px-4 fw-bold"><i class="bi bi-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
