<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel - FarmReach Cloud')</title>

    <!-- Google Fonts: Hanken Grotesk & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/farmreach-theme.css?v=2.1">

    <style>
        :root {
            --admin-sidebar-width: 260px;
        }

        body.admin-body {
            background-color: #F4F6F4;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Dedicated Left Vertical Sidebar (Matching Wireframe pages 24-26) */
        .admin-sidebar {
            width: var(--admin-sidebar-width);
            background-color: #1B4332;
            color: #ffffff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 16px rgba(0, 0, 0, 0.12);
            transition: transform 0.3s ease-in-out;
        }

        .admin-sidebar-header {
            padding: 1.75rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-sidebar-brand {
            font-family: 'Hanken Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-nav-list {
            list-style: none;
            padding: 1.25rem 0.75rem;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
        }

        .admin-nav-item {
            margin-bottom: 0.5rem;
        }

        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.8rem 1.1rem;
            color: rgba(255, 255, 255, 0.85);
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .admin-nav-link i {
            font-size: 1.15rem;
            width: 22px;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

        .admin-nav-link:hover {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }

        .admin-nav-link:hover i {
            color: #DAA520;
        }

        .admin-nav-link.active {
            background-color: #2D6A4F;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-left: 4px solid #DAA520;
        }

        .admin-nav-link.active i {
            color: #DAA520;
        }

        .admin-sidebar-footer {
            padding: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background-color: rgba(0, 0, 0, 0.15);
        }

        /* Right Content Area */
        .admin-main-wrapper {
            margin-left: var(--admin-sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease-in-out;
        }

        .admin-topbar {
            background-color: #ffffff;
            border-bottom: 1px solid #E2E8F0;
            padding: 0.85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .admin-content-body {
            padding: 2rem;
            flex-grow: 1;
        }

        /* Responsive Mobile Breakpoint */
        @media (max-width: 991.98px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.show-sidebar {
                transform: translateX(0);
            }
            .admin-main-wrapper {
                margin-left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1035;
            }
            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>

    @stack('styles')
</head>
<body class="admin-body">
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Vertical Left Sidebar (Exact Wireframe match) -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Sidebar Brand Header -->
        <div class="admin-sidebar-header">
            <a href="{{ route('admin.dashboard') }}" class="admin-sidebar-brand">
                <i class="bi bi-tree-fill text-warning"></i>
                <span>FarmReach</span>
            </a>
            <span class="badge bg-warning text-dark px-2 py-0 mt-2 small fw-bold" style="font-size: 11px;">
                Administrator Mode
            </span>
        </div>

        <!-- Vertical Navigation Links -->
        <ul class="admin-nav-list">
            <li class="admin-nav-item">
                <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="admin-nav-item">
                <a href="{{ route('admin.users') }}" class="admin-nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i>
                    <span>Manage Users</span>
                </a>
            </li>

            <li class="admin-nav-item">
                <a href="{{ route('admin.announcements') }}" class="admin-nav-link {{ request()->routeIs('admin.announcements') ? 'active' : '' }}">
                    <i class="bi bi-megaphone-fill"></i>
                    <span>Announcements</span>
                </a>
            </li>

            <li class="admin-nav-item">
                <a href="{{ route('admin.recipes') }}" class="admin-nav-link {{ request()->routeIs('admin.recipes') ? 'active' : '' }}">
                    <i class="bi bi-book-half"></i>
                    <span>Manage Recipes</span>
                </a>
            </li>

            <li class="admin-nav-item">
                <a href="{{ route('profile.edit') }}" class="admin-nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                    <i class="bi bi-person-fill-gear"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <li class="admin-nav-item mt-3 pt-3 border-top border-white border-opacity-10">
                <a href="{{ route('marketplace') }}" class="admin-nav-link text-white text-opacity-75" target="_blank">
                    <i class="bi bi-shop"></i>
                    <span>Public Marketplace</span>
                    <i class="bi bi-box-arrow-up-right ms-auto small" style="font-size: 12px;"></i>
                </a>
            </li>
        </ul>

        <!-- Sidebar Bottom Footer with User & Log out (Wireframe page 24-26) -->
        <div class="admin-sidebar-footer">
            <a href="{{ route('profile.edit') }}" class="d-flex align-items-center gap-2 mb-3 text-decoration-none text-white" title="Edit Profile">
                @if(Auth::user()->avatar_path)
                    <img src="{{ Auth::user()->avatar_path }}" alt="{{ Auth::user()->name }}" class="rounded-circle border border-white flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;">
                @else
                    <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 38px; height: 38px;">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                @endif
                <div class="overflow-hidden">
                    <div class="fw-bold small text-white text-truncate">{{ Auth::user()->name }}</div>
                    <small class="text-white text-opacity-50 d-block text-truncate" style="font-size: 11px;">Administrator</small>
                </div>
            </a>

            <form action="{{ route('logout') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm w-100 fw-bold d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-box-arrow-left"></i> Log out
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main-wrapper">
        <!-- Top Sticky Bar -->
        <header class="admin-topbar">
            <div class="d-flex align-items-center gap-3">
                <!-- Mobile toggle button -->
                <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" id="btnToggleSidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h5 class="fw-bold mb-0 text-farm-primary font-headline">
                    @yield('header_title', 'Administrator View')
                </h5>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-light text-dark border d-none d-sm-inline">
                    <i class="bi bi-clock me-1"></i> {{ now()->format('M d, Y') }}
                </span>
                <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-farm-outline py-1 px-3 d-flex align-items-center gap-2">
                    @if(Auth::user()->avatar_path)
                        <img src="{{ Auth::user()->avatar_path }}" alt="{{ Auth::user()->name }}" class="rounded-circle border" style="width: 20px; height: 20px; object-fit: cover;">
                    @else
                        <i class="bi bi-person me-1"></i>
                    @endif
                    <span>Profile</span>
                </a>
            </div>
        </header>



        <!-- Yield Page Content -->
        <main class="admin-content-body">
            @yield('content')
        </main>
    </div>

    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5.3.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- FarmReach Custom App Script -->
    <script src="/js/farmreach-app.js?v=2.1"></script>

    <script>
        $(document).ready(function () {
            // Mobile sidebar toggle
            $('#btnToggleSidebar').on('click', function () {
                $('#adminSidebar').toggleClass('show-sidebar');
                $('#sidebarOverlay').toggleClass('active');
            });

            $('#sidebarOverlay').on('click', function () {
                $('#adminSidebar').removeClass('show-sidebar');
                $(this).removeClass('active');
            });

            // SweetAlert2 Toast Notifications
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
        });
    </script>

    @stack('scripts')
</body>
</html>
