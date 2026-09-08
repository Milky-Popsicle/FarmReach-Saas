<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'FarmReach Cloud')</title>

    <!-- Google Fonts: Hanken Grotesk & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/farmreach-theme.css?v=2.1">
</head>
<body class="auth-wrapper">
    <div class="auth-card p-4 p-md-5">
        <div class="text-center mb-4">
            <a href="{{ route('marketplace') }}" class="text-decoration-none">
                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle bg-farm-primary text-warning mb-2" style="width: 60px; height: 60px; background-color: #1B4332;">
                    <i class="bi bi-tree-fill fs-2"></i>
                </div>
                <h2 class="fw-bold mb-0 text-farm-primary">FarmReach</h2>
            </a>
            <p class="text-muted small mt-1">Helping farmers reach buyers &bull; Agricultural SaaS</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger py-2 small">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <!-- jQuery 3.7.1 & Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/js/farmreach-app.js?v=2.1"></script>

    <script>
        // Universal instant password show/hide toggle for auth pages (Zero external dependencies, mobile & desktop optimized)
        window.togglePasswordVisibility = function (trigger, event) {
            if (event) {
                if (typeof event.preventDefault === 'function') event.preventDefault();
                if (typeof event.stopPropagation === 'function') event.stopPropagation();
            }

            const btn = (trigger && trigger.closest) ? trigger.closest('.btn-toggle-password') : trigger;
            if (!btn) return;

            const targetSelector = btn.getAttribute('data-target') || btn.getAttribute('data-bs-target');
            let input = null;
            if (targetSelector) {
                try {
                    input = document.querySelector(targetSelector);
                } catch (err) {}
            }
            if (!input) {
                const group = btn.closest('.input-group') || btn.parentElement;
                if (group) {
                    input = group.querySelector('input[type="password"], input[type="text"]');
                }
            }
            if (!input) return;

            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');

            const icon = btn.querySelector('i') || btn.querySelector('.bi');
            if (icon) {
                if (isPassword) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash-fill');
                } else {
                    icon.classList.remove('bi-eye-slash-fill', 'bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }

            if (isPassword) {
                btn.classList.add('is-active');
                btn.setAttribute('title', 'Hide Password');
                btn.setAttribute('aria-label', 'Hide Password');
                btn.setAttribute('aria-pressed', 'true');
            } else {
                btn.classList.remove('is-active');
                btn.setAttribute('title', 'Show Password');
                btn.setAttribute('aria-label', 'Show Password');
                btn.setAttribute('aria-pressed', 'false');
            }
        };

        // Attach native click & touch delegation in vanilla JS
        document.addEventListener('click', function (e) {
            const btn = e.target.closest ? e.target.closest('.btn-toggle-password') : null;
            if (btn) {
                window.togglePasswordVisibility(btn, e);
            }
        });

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "{{ session('success') }}",
                confirmButtonColor: '#1B4332'
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Attention',
                text: "{{ session('error') }}",
                confirmButtonColor: '#1B4332'
            });
        @endif
    </script>

    @stack('scripts')
</body>
</html>
