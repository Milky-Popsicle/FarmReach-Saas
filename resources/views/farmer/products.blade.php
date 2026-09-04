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
                                <span class="text-muted">Quantity:</span>
                                <strong>{{ $prod->quantity }} {{ $prod->unit }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted">Price:</span>
                                <strong class="text-success">₱{{ number_format($prod->price, 2) }}</strong>
                            </div>
                            <div class="col-12">
                                <span class="text-muted">Weight per Unit:</span>
                                <strong>{{ $prod->kilograms }} kg</strong>
                            </div>
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
                                    data-kilograms="{{ $prod->kilograms }}"
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
                                        &bull; <strong>{{ $it->quantity }}x</strong> {{ $it->product->name ?? 'Produce' }}
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
                                <small class="text-muted d-block" style="font-size: 10px;">{{ ucfirst($ord->delivery_method) }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $ord->status_badge_class }} text-capitalize">
                                    {{ $ord->status }}
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Update Status
                                    </button>
                                    <ul class="dropdown-menu shadow-sm">
                                        <li>
                                            <form action="{{ route('farmer.orders.status', $ord) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" class="dropdown-item text-primary"><i class="bi bi-check-circle me-1"></i> Confirm Order</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('farmer.orders.status', $ord) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="status" value="delivered">
                                                <button type="submit" class="dropdown-item text-success"><i class="bi bi-truck me-1"></i> Mark as Delivered</button>
                                            </form>
                                        </li>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content farm-card border-0">
            <div class="modal-header modal-header-farm">
                <h5 class="modal-title fw-bold">Add a Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('farmer.products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold small">Product Name</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Sayote, Cabbage, Carabao Mango" required>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label fw-bold small">Category</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="quantity" class="form-label fw-bold small">Quantity (Stock)</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" placeholder="e.g. 50" min="0" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="price" class="form-label fw-bold small">Price (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" placeholder="e.g. 45.00" min="1" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="kilograms" class="form-label fw-bold small">Kilograms (Weight per unit)</label>
                            <input type="number" step="0.01" class="form-control" id="kilograms" name="kilograms" value="1.00" min="0.1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="unit" class="form-label fw-bold small">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" value="kg" placeholder="e.g. kg, sack, bundle">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold small">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Describe the harvest freshness, variety, or special care..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label fw-bold small">Attach Image (Optional)</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-farm-primary px-4 fw-bold">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Product (Wireframe page 28) -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content farm-card border-0">
            <div class="modal-header modal-header-farm">
                <h5 class="modal-title fw-bold">Edit a Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label fw-bold small">Product Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label fw-bold small">Category</label>
                        <select class="form-select" id="edit_category_id" name="category_id" required>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="edit_quantity" class="form-label fw-bold small">Quantity</label>
                            <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="edit_price" class="form-label fw-bold small">Price (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="edit_price" name="price" min="1" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label for="edit_kilograms" class="form-label fw-bold small">Kilograms</label>
                            <input type="number" step="0.01" class="form-control" id="edit_kilograms" name="kilograms" min="0.1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label for="edit_unit" class="form-label fw-bold small">Unit</label>
                            <input type="text" class="form-control" id="edit_unit" name="unit">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label fw-bold small">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_image" class="form-label fw-bold small">Attach New Image (Optional)</label>
                        <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-farm-primary px-4 fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
