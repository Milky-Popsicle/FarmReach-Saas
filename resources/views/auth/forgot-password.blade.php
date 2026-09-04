@extends('layouts.auth')

@section('title', 'Forgot Password - FarmReach Cloud')

@section('content')
<div class="text-center mb-3">
    <h5 class="fw-bold">Password Recovery</h5>
    <p class="text-muted small">Enter your registered email address or phone number, and we will send recovery instructions.</p>
</div>

<form action="{{ route('password.email') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label for="login_id" class="form-label fw-semibold small text-dark">Email or Phone Number</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="text" class="form-control border-start-0" id="login_id" name="login_id" value="{{ old('login_id') }}" placeholder="Registered email or phone" required autofocus>
        </div>
    </div>

    <button type="submit" class="btn btn-farm-primary w-100 py-2 fw-bold mb-3 shadow-sm" style="background-color: #1B4332;">
        Send Recovery Instructions
    </button>

    <div class="text-center">
        <a href="{{ route('login') }}" class="small fw-bold text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i> Return to Sign In
        </a>
    </div>
</form>
@endsection
