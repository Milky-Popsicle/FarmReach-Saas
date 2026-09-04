<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FarmReach Cloud - Agricultural Marketplace & SaaS')</title>

    <!-- Google Fonts: Hanken Grotesk & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- FarmReach Custom Theme CSS -->
    <link rel="stylesheet" href="/css/farmreach-theme.css">

    @stack('styles')
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg farm-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand" href="{{ route('marketplace') }}">
                <i class="bi bi-tree-fill text-warning"></i>
                <span>FarmReach</span>
                <span class="badge-accent">SaaS</span>
            </a>

            <button class="navbar-toggler text-white border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <i class="bi bi-list fs-2"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('marketplace*') ? 'active' : '' }}" href="{{ route('marketplace') }}">
                            <i class="bi bi-shop me-1"></i> Marketplace
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('marketplace.compare') ? 'active' : '' }}" href="{{ route('marketplace.compare') }}">
                            <i class="bi bi-bar-chart-steps me-1"></i> Price Compare
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('recipes*') ? 'active' : '' }}" href="{{ route('recipes.index') }}">
                            <i class="bi bi-book-half me-1"></i> Recipe Shopping
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('announcements*') ? 'active' : '' }}" href="{{ route('announcements.index') }}">
                            <i class="bi bi-megaphone me-1"></i> Announcements
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <!-- Cart Button -->
                    @php $cartCount = count(session('cart', [])); @endphp
                    <li class="nav-item">
                        <a href="{{ route('cart.index') }}" class="btn btn-outline-light position-relative px-3 py-1 rounded-pill">
                            <i class="bi bi-cart3 me-1"></i> Cart
                            <span class="cart-badge-count badge rounded-pill bg-warning text-dark ms-1" style="{{ $cartCount > 0 ? '' : 'display:none;' }}">
                                {{ $cartCount }}
                            </span>
                        </a>
                    </li>

                    @guest
                        <li class="nav-item">
                            <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light rounded-pill px-3">Sign In</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('register') }}" class="btn btn-sm btn-farm-accent rounded-pill px-3 text-dark font-weight-bold">Register</a>
                        </li>
                    @else
                        @if(Auth::user()->isFarmer())
                            <li class="nav-item">
                                <a href="{{ route('farmer.marketplace') }}" class="btn btn-sm btn-farm-accent rounded-pill px-3 text-dark fw-bold">
                                    <i class="bi bi-speedometer2 me-1"></i> Farmer Hub
                                </a>
                            </li>
                        @elseif(Auth::user()->isAdmin())
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-warning rounded-pill px-3 text-dark fw-bold">
                                    <i class="bi bi-shield-lock me-1"></i> Admin Panel
                                </a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a href="{{ route('buyer.orders') }}" class="btn btn-sm btn-outline-light rounded-pill px-3">
                                    <i class="bi bi-bag-check me-1"></i> My Orders
                                </a>
                            </li>
                        @endif

                        <!-- User Profile Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 13px;">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                                <span class="d-none d-md-inline">{{ Auth::user()->name }}</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li class="px-3 py-1 text-muted small">
                                    Signed in as <strong class="text-capitalize">{{ Auth::user()->role }}</strong>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                        <i class="bi bi-person-gear me-2"></i> My Profile
                                    </a>
                                </li>
                                @if(Auth::user()->isFarmer())
                                    <li><a class="dropdown-item" href="{{ route('farmer.products') }}"><i class="bi bi-box-seam me-2"></i> My Products</a></li>
                                    <li><a class="dropdown-item" href="{{ route('farmer.orders') }}"><i class="bi bi-receipt me-2"></i> Incoming Orders</a></li>
                                @elseif(Auth::user()->isAdmin())
                                    <li><a class="dropdown-item" href="{{ route('admin.users') }}"><i class="bi bi-people me-2"></i> Manage Users</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.announcements') }}"><i class="bi bi-megaphone me-2"></i> Announcements</a></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.recipes') }}"><i class="bi bi-book me-2"></i> Recipes</a></li>
                                @else
                                    <li><a class="dropdown-item" href="{{ route('buyer.orders') }}"><i class="bi bi-card-checklist me-2"></i> My Orders</a></li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i> Log out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Notifications -->
    <div class="container mt-3">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('info'))
            <div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                <div>{{ session('info') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
    </div>

    <!-- Main Content Area -->
    <main class="flex-grow-1 mb-5">
        @yield('content')
    </main>

    <!-- Global "Added to Cart / Orders" Modal (as required by Wireframe page 31) -->
    <div class="modal fade" id="addedToCartModal" tabindex="-1" aria-labelledby="addedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content farm-card text-center p-4 border-0">
                <div class="modal-body">
                    <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-bag-check-fill fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Added to your orders!</h4>
                    <p class="text-muted mb-4">
                        <strong id="addedProductQty">1</strong> unit(s) of <strong id="addedProductName">Product</strong> added to your cart.
                    </p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-farm-outline px-4" data-bs-dismiss="modal">Continue Shopping</button>
                        <a href="{{ route('cart.index') }}" class="btn btn-farm-accent px-4 text-dark fw-bold">Go to Cart / Orders &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-top py-4 mt-auto">
        <div class="container text-center text-md-start">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <span class="fw-bold text-farm-primary fs-5">FarmReach Cloud</span>
                    <p class="text-muted small mb-0">A SaaS-Driven Agricultural Marketplace & Order Management Platform.</p>
                    <p class="text-muted small">Surigao del Norte State University &bull; CCIS Capstone Project</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted small me-3">&copy; {{ date('Y') }} FarmReach Cloud. All rights reserved.</span>
                    <a href="{{ route('marketplace') }}" class="text-decoration-none text-muted small me-2">Marketplace</a>
                    <a href="{{ route('recipes.index') }}" class="text-decoration-none text-muted small me-2">Recipes</a>
                    <a href="{{ route('announcements.index') }}" class="text-decoration-none text-muted small">Announcements</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5.3.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- FarmReach Custom App Script -->
    <script src="/js/farmreach-app.js"></script>

    @stack('scripts')
</body>
</html>
