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
    <link rel="stylesheet" href="/css/farmreach-theme.css">
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
</body>
</html>
