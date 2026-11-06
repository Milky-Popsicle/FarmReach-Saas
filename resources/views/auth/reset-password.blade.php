@extends('layouts.auth')

@section('title', 'Reset Password - FarmReach Cloud')

@section('content')
<div class="text-center mb-3">
    <h5 class="fw-bold">Reset Password</h5>
    <p class="text-muted small">Choose a new password for your FarmReach account.</p>
</div>

<form id="resetPasswordForm" action="{{ route('password.update') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="mb-3">
        <label for="email" class="form-label fw-semibold small text-dark">Registered Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" class="form-control border-start-0" id="email" name="email" value="{{ old('email', $email) }}" autocomplete="username" readonly required>
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label fw-semibold small text-dark">New Password</label>
        <div class="input-group flex-nowrap">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Enter a new password" autocomplete="new-password" required autofocus>
            <button type="button" class="btn btn-toggle-password" data-target="#password" onclick="togglePasswordVisibility(this, event)" title="Show Password" aria-label="Show Password">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <div class="form-text">Use at least 6 characters.</div>
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label fw-semibold small text-dark">Confirm New Password</label>
        <div class="input-group flex-nowrap">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" class="form-control border-start-0 border-end-0" id="password_confirmation" name="password_confirmation" placeholder="Repeat the new password" autocomplete="new-password" required>
            <button type="button" class="btn btn-toggle-password" data-target="#password_confirmation" onclick="togglePasswordVisibility(this, event)" title="Show Password" aria-label="Show Password">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <button type="submit" id="btnResetPassword" class="btn btn-farm-primary w-100 py-2 fw-bold mb-3 shadow-sm" style="background-color: #1B4332;">
        Reset Password
    </button>

    <div class="text-center">
        <a href="{{ route('login') }}" class="small fw-bold text-decoration-none text-muted">
            <i class="bi bi-arrow-left me-1"></i> Return to Sign In
        </a>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('resetPasswordForm');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function () {
            const button = document.getElementById('btnResetPassword');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Resetting...';
        });
    });
</script>
@endpush
