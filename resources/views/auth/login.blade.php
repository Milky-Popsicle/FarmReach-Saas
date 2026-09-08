@extends('layouts.auth')

@section('title', 'Sign In - FarmReach Cloud')

@section('content')
<form id="loginForm" action="{{ route('login.submit') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label for="login_id" class="form-label fw-semibold small text-dark">Email or Phone Number</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
            <input type="text" class="form-control border-start-0" id="login_id" name="login_id" value="{{ old('login_id') }}" placeholder="e.g., admin@farmreach.ph or 09123456789" required autofocus>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <label for="password" class="form-label fw-semibold small text-dark mb-0">Password</label>
            <a href="{{ route('password.request') }}" class="small text-decoration-none text-muted">Forgot password?</a>
        </div>
        <div class="input-group mt-1 flex-nowrap">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="btn btn-toggle-password" data-target="#password" onclick="togglePasswordVisibility(this, event)" title="Show Password" aria-label="Show Password">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
        <label class="form-check-label small text-muted" for="remember">
            Keep me signed in
        </label>
    </div>

    <button type="submit" id="btnSubmitLogin" class="btn btn-farm-primary w-100 py-2 fw-bold mb-3 shadow-sm" style="background-color: #1B4332;">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
    </button>

    <div class="text-center">
        <span class="small text-muted">Don't have an account?</span>
        <a href="{{ route('register') }}" class="small fw-bold text-decoration-none text-success">Create Account</a>
    </div>
</form>
@endsection

