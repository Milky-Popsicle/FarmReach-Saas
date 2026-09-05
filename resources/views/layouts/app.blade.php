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
    <nav class="navbar navbar-expand-lg farm-navbar sticky-top py-2">
        <div class="container">
            <a class="navbar-brand py-0 me-3" href="{{ route('marketplace') }}">
                <i class="bi bi-tree-fill text-warning fs-5"></i>
                <span class="fw-bold">FarmReach</span>
                <span class="badge-accent ms-1">SaaS</span>
            </a>

            <button class="navbar-toggler text-white border-0 p-1" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <i class="bi bi-list fs-3"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">
                    <li class="nav-item">
                        <a class="nav-link py-1 px-3 rounded-pill {{ (request()->routeIs('marketplace') || request()->routeIs('marketplace.show')) ? 'active' : '' }}" href="{{ route('marketplace') }}">
                            <i class="bi bi-shop me-1"></i> Marketplace
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1 px-3 rounded-pill {{ request()->routeIs('marketplace.compare') ? 'active' : '' }}" href="{{ route('marketplace.compare') }}">
                            <i class="bi bi-bar-chart-steps me-1"></i> Price Compare
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1 px-3 rounded-pill {{ request()->routeIs('recipes*') ? 'active' : '' }}" href="{{ route('recipes.index') }}">
                            <i class="bi bi-book-half me-1"></i> Recipe Shopping
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1 px-3 rounded-pill {{ request()->routeIs('announcements*') ? 'active' : '' }}" href="{{ route('announcements.index') }}">
                            <i class="bi bi-megaphone me-1"></i> Announcements
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <!-- Cart Button -->
                    @php $cartCount = count(session('cart', [])); @endphp
                    <li class="nav-item">
                        <a href="{{ route('cart.index') }}" class="btn btn-sm btn-outline-light position-relative px-3 py-1 rounded-pill d-flex align-items-center gap-1">
                            <i class="bi bi-cart3"></i>
                            <span class="small fw-semibold">Cart</span>
                            <span class="cart-badge-count badge rounded-pill bg-warning text-dark ms-1" style="{{ $cartCount > 0 ? '' : 'display:none;' }}">
                                {{ $cartCount }}
                            </span>
                        </a>
                    </li>

                    @guest
                        <li class="nav-item">
                            <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1">Sign In</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('register') }}" class="btn btn-sm btn-farm-accent rounded-pill px-3 py-1 text-dark fw-bold">Register</a>
                        </li>
                    @else
                        @if(Auth::user()->isFarmer())
                            <li class="nav-item">
                                <a href="{{ route('farmer.marketplace') }}" class="btn btn-sm btn-farm-accent rounded-pill px-3 py-1 text-dark fw-bold d-flex align-items-center gap-1">
                                    <i class="bi bi-speedometer2"></i>
                                    <span>Farmer Hub</span>
                                </a>
                            </li>
                        @elseif(Auth::user()->isAdmin())
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-warning rounded-pill px-3 py-1 text-dark fw-bold d-flex align-items-center gap-1">
                                    <i class="bi bi-shield-lock"></i>
                                    <span>Admin Panel</span>
                                </a>
                            </li>
                        @else
                            <li class="nav-item">
                                <a href="{{ route('buyer.orders') }}" class="btn btn-sm btn-outline-light rounded-pill px-3 py-1 d-flex align-items-center gap-1">
                                    <i class="bi bi-bag-check"></i>
                                    <span>My Orders</span>
                                </a>
                            </li>
                        @endif

                        <!-- User Profile Dropdown -->
                        <li class="nav-item dropdown ms-1">
                            <a class="nav-link py-1 px-2 rounded-pill text-white d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                @if(Auth::user()->avatar_path)
                                    <img src="{{ Auth::user()->avatar_path }}" alt="{{ Auth::user()->name }}" class="rounded-circle border border-white" style="width: 30px; height: 30px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center fw-bold" style="width: 30px; height: 30px; font-size: 12px;">
                                        {{ substr(Auth::user()->name, 0, 1) }}
                                    </div>
                                @endif
                                <span class="d-none d-md-inline small fw-semibold">{{ Auth::user()->name }}</span>
                                <i class="bi bi-chevron-down opacity-75" style="font-size: 10px;"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                                <li class="px-3 py-2 bg-light border-bottom rounded-top">
                                    <div class="fw-bold small text-dark">{{ Auth::user()->name }}</div>
                                    <div class="text-muted mt-1" style="font-size: 11px;">
                                        Role: <span class="badge {{ Auth::user()->isFarmer() ? 'bg-success' : (Auth::user()->isAdmin() ? 'bg-danger' : 'bg-primary') }} text-white text-capitalize py-1 px-2 fw-bold" style="font-size: 10px;">{{ Auth::user()->role }}</span>
                                    </div>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="{{ route('profile.edit') }}">
                                        <i class="bi bi-person-gear me-2 text-muted"></i> My Profile
                                    </a>
                                </li>
                                @if(Auth::user()->isFarmer())
                                    <li><a class="dropdown-item py-2" href="{{ route('farmer.products') }}"><i class="bi bi-box-seam me-2 text-muted"></i> My Products</a></li>
                                    <li><a class="dropdown-item py-2" href="{{ route('farmer.orders') }}"><i class="bi bi-receipt me-2 text-muted"></i> Incoming Orders</a></li>
                                    <li><a class="dropdown-item py-2" href="{{ route('buyer.orders') }}"><i class="bi bi-bag-check me-2 text-muted"></i> My Purchases</a></li>
                                @elseif(Auth::user()->isAdmin())
                                    <li><a class="dropdown-item py-2" href="{{ route('admin.users') }}"><i class="bi bi-people me-2 text-muted"></i> Manage Users</a></li>
                                    <li><a class="dropdown-item py-2" href="{{ route('admin.announcements') }}"><i class="bi bi-megaphone me-2 text-muted"></i> Announcements</a></li>
                                    <li><a class="dropdown-item py-2" href="{{ route('admin.recipes') }}"><i class="bi bi-book me-2 text-muted"></i> Recipes</a></li>
                                @else
                                    <li><a class="dropdown-item py-2" href="{{ route('buyer.orders') }}"><i class="bi bi-card-checklist me-2 text-muted"></i> My Orders</a></li>
                                @endif
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="dropdown-item py-2 text-danger">
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

    <!-- Global Image Lightbox Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content farm-card border-0 overflow-hidden">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-farm-primary" id="imagePreviewModalTitle">Crop Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-3">
                    <img src="" id="imagePreviewModalSrc" class="img-fluid rounded-3 shadow" alt="Preview Image" style="max-height: 75vh; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Back-to-Top Button -->
    <button type="button" id="btnBackToTop" class="btn-back-to-top" title="Back to Top" aria-label="Back to Top">
        <i class="bi bi-arrow-up-short"></i>
    </button>


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

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- FarmReach Custom App Script -->
    <script src="/js/farmreach-app.js"></script>

    <!-- SweetAlert2 Flash Notifications Trigger -->
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: @json(session('success'))
            });
        @endif

        @if(session('info'))
            Toast.fire({
                icon: 'info',
                title: @json(session('info'))
            });
        @endif

        @if(session('warning'))
            Toast.fire({
                icon: 'warning',
                title: @json(session('warning'))
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Attention',
                text: @json(session('error')),
                confirmButtonColor: '#1B4332'
            });
        @endif
    </script>

    @stack('scripts')
</body>
</html>
