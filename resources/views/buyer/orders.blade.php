@extends('layouts.app')

@section('title', 'My Orders - FarmReach Cloud')

@section('content')
<div class="container py-4">
    <!-- Header Banner -->
    <div class="farm-card p-4 mb-4 bg-light border-0" style="background: linear-gradient(135deg, #1B4332 0%, #2D6A4F 100%); color: white;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold text-uppercase mb-2">
                    Buyer Portal
                </span>
                <h2 class="fw-bold text-white mb-1">Welcome, {{ Auth::user()->name }}!</h2>
                <p class="text-light mb-0">Here you can manage, track, and reorder your farm harvests.</p>
            </div>
            <div>
                <a href="{{ route('marketplace') }}" class="btn btn-farm-accent text-dark fw-bold">
                    <i class="bi bi-plus-circle me-1"></i> New Order
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Orders Column -->
        <div class="col-lg-8">
            <div class="farm-card p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-3 border-bottom">
                    <div>
                        <h4 class="fw-bold mb-0 text-farm-primary">My Orders</h4>
                        <span class="text-muted small">Showing {{ $orders->count() }} orders</span>
                    </div>

                    <!-- Dual View Toggle Buttons (Card View vs Table View per Wireframe) -->
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted fw-bold d-none d-sm-inline">View:</span>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-farm-primary active" id="btnCardView">
                                <i class="bi bi-grid-fill me-1"></i> Card View
                            </button>
                            <button type="button" class="btn btn-sm btn-farm-outline" id="btnTableView">
                                <i class="bi bi-table me-1"></i> Table View
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Status Filter Pills & Search -->
                <div class="row g-2 mb-4">
                    <div class="col-md-7">
                        <div class="d-flex flex-wrap gap-1 nav-pills-farm">
                            <a href="{{ route('buyer.orders', ['status' => 'all']) }}" class="nav-link {{ $currentStatus === 'all' ? 'active' : '' }}">
                                All ({{ $totalOrdersCount }})
                            </a>
                            <a href="{{ route('buyer.orders', ['status' => 'pending']) }}" class="nav-link {{ $currentStatus === 'pending' ? 'active' : '' }}">
                                Pending ({{ $pendingCount }})
                            </a>
                            <a href="{{ route('buyer.orders', ['status' => 'confirmed']) }}" class="nav-link {{ $currentStatus === 'confirmed' ? 'active' : '' }}">
                                Confirmed ({{ $confirmedCount }})
                            </a>
                            <a href="{{ route('buyer.orders', ['status' => 'delivered']) }}" class="nav-link {{ $currentStatus === 'delivered' ? 'active' : '' }}">
                                Delivered ({{ $deliveredCount }})
                            </a>
                            <a href="{{ route('buyer.orders', ['status' => 'cancelled']) }}" class="nav-link {{ $currentStatus === 'cancelled' ? 'active' : '' }}">
                                Cancelled ({{ $cancelledCount }})
                            </a>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <form action="{{ route('buyer.orders') }}" method="GET" class="d-flex gap-1">
                            <input type="hidden" name="status" value="{{ $currentStatus }}">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Search order ID or crop..." value="{{ request('search') }}">
                                <button type="submit" class="btn btn-farm-primary"><i class="bi bi-search"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 1. Card View Container (Wireframe page 31) -->
                <div id="ordersCardContainer" class="d-flex flex-column gap-3">
                    @forelse($orders as $ord)
                        <div class="farm-card p-3 border">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <div>
                                    <span class="fw-bold text-dark me-2">#{{ $ord->order_number }}</span>
                                    @if($ord->isBulk())
                                        <span class="badge bg-warning text-dark me-2"><i class="bi bi-box-fill me-1"></i>Bulk Order</span>
                                    @endif
                                    <small class="text-muted"><i class="bi bi-calendar3"></i> {{ $ord->created_at->format('M d, Y h:i A') }}</small>
                                </div>
                                <div>
                                    <span class="badge {{ $ord->status_badge_class }} px-3 py-1 text-capitalize">
                                        {{ $ord->status }}
                                    </span>
                                </div>
                            </div>

                            <div class="row align-items-center g-3">
                                <div class="col-md-7">
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-person-fill text-success"></i> Farmer: <strong>{{ $ord->farmer->name }}</strong>
                                        <span class="d-block text-truncate" style="font-size: 11px;"><i class="bi bi-geo-alt"></i> {{ $ord->farmer->address }}</span>
                                    </div>
                                    <div class="my-2">
                                        @foreach($ord->items as $it)
                                            <div class="small text-dark">
                                                &bull; <strong>{{ $it->quantity }}x</strong> {{ $it->product->name ?? 'Produce' }}
                                                <span class="text-muted">(₱{{ number_format($it->unit_price, 2) }} each)</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="small text-muted">
                                        Method: <strong>{{ ucfirst($ord->delivery_method) }}</strong> &bull; Payment: <strong class="text-uppercase">{{ $ord->payment?->payment_method ?? 'COD' }}</strong>
                                    </div>
                                </div>

                                <div class="col-md-5 text-md-end">
                                    <div class="mb-2">
                                        <span class="text-muted small">Total: </span>
                                        <span class="fs-5 fw-bold text-farm-primary">₱{{ number_format($ord->total_amount, 2) }}</span>
                                    </div>
                                    <div class="d-flex gap-2 justify-content-md-end">
                                        @if($ord->status === 'pending')
                                            <form action="{{ route('buyer.orders.cancel', $ord) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Order</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('buyer.orders.reorder', $ord) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-farm-primary">
                                                <i class="bi bi-arrow-repeat me-1"></i> Reorder
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                            <p>No orders found matching the filter.</p>
                        </div>
                    @endforelse
                </div>

                <!-- 2. Table View Container (Wireframe page 32) -->
                <div id="ordersTableContainer" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle farm-table mb-0">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Product Details</th>
                                    <th>Seller (Farmer)</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $ord)
                                    <tr>
                                        <td>
                                            <strong class="text-dark small">#{{ $ord->order_number }}</strong>
                                            @if($ord->isBulk())
                                                <span class="badge bg-warning text-dark d-block" style="font-size: 9px;">Bulk</span>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($ord->items as $it)
                                                <div class="small fw-semibold text-truncate" style="max-width: 140px;">
                                                    {{ $it->product->name ?? 'Crop' }}
                                                </div>
                                            @endforeach
                                        </td>
                                        <td>
                                            <div class="small">{{ $ord->farmer->name }}</div>
                                            <div class="text-muted" style="font-size: 10px;">{{ $ord->farmer->address }}</div>
                                        </td>
                                        <td class="small">{{ $ord->items->sum('quantity') }}</td>
                                        <td>
                                            <strong class="text-farm-primary small">₱{{ number_format($ord->total_amount, 2) }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge {{ $ord->status_badge_class }} small text-capitalize">
                                                {{ $ord->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                @if($ord->status === 'pending')
                                                    <form action="{{ route('buyer.orders.cancel', $ord) }}" method="POST" onsubmit="return confirm('Cancel this order?')">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Cancel">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                <form action="{{ route('buyer.orders.reorder', $ord) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-farm-primary py-0 px-2" title="Reorder">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No orders found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Order Summary (Wireframe page 31 & 32) -->
        <div class="col-lg-4">
            <div class="farm-card p-4 mb-4">
                <h5 class="fw-bold text-farm-primary mb-3 border-bottom pb-2">Order Summary</h5>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Orders:</span>
                    <strong>{{ $totalOrdersCount }}</strong>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Pending Confirmation:</span>
                    <strong class="text-warning">{{ $pendingCount }}</strong>
                </div>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Delivered / Completed:</span>
                    <strong class="text-success">{{ $deliveredCount }}</strong>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Cancelled Orders:</span>
                    <strong class="text-danger">{{ $cancelledCount }}</strong>
                </div>

                <a href="{{ route('cart.index') }}" class="btn btn-farm-primary w-100 py-2 fw-bold shadow-sm">
                    <i class="bi bi-cart3 me-1"></i> Go to Cart / Checkout
                </a>
            </div>

            <!-- Quick Announcements Widget -->
            <div class="farm-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-farm-primary"><i class="bi bi-megaphone-fill text-warning me-1"></i> Announcements</h6>
                    <a href="{{ route('announcements.index') }}" class="small text-muted text-decoration-none">View All</a>
                </div>
                <p class="text-muted small mb-0">Stay updated on upcoming local agri-trade fairs, organic workshops, and harvest sales in Surigao.</p>
            </div>
        </div>
    </div>

    <!-- Recommended Products Section (Wireframe page 31 & 32: "Recommended Products" at bottom) -->
    <div class="mt-4">
        <h4 class="fw-bold mb-3 text-farm-primary"><i class="bi bi-hand-thumbs-up-fill text-warning me-2"></i>Recommended Products</h4>
        <div class="row g-3">
            @foreach($recommendedProducts as $rec)
                <div class="col-6 col-md-3">
                    <div class="farm-card h-100 p-3 farm-card-interactive d-flex flex-column">
                        <img src="{{ $rec->image_path }}" class="rounded-3 mb-2" alt="{{ $rec->name }}" style="height: 120px; object-fit: cover;">
                        <h6 class="fw-bold mb-1 text-truncate">{{ $rec->name }}</h6>
                        <small class="text-muted d-block mb-2">{{ $rec->farmer->name }}</small>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <strong class="text-farm-primary">₱{{ number_format($rec->price, 2) }}</strong>
                            <form action="{{ route('cart.add') }}" method="POST" class="form-add-to-cart m-0">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $rec->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-sm btn-farm-primary py-0 px-2" title="Add to Cart">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
