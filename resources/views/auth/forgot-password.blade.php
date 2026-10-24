@extends('layouts.auth')

@section('title', 'Forgot Password - FarmReach Cloud')

@section('content')
<div class="text-center mb-3">
    <h5 class="fw-bold">Password Recovery</h5>
    <p class="text-muted small">Enter your registered email address. Phone recovery requires SMS verification, which is not currently enabled.</p>
</div>

<form id="forgotPasswordForm" action="{{ route('password.email') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label for="login_id" class="form-label fw-semibold small text-dark">Registered Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="text" class="form-control border-start-0" id="login_id" name="login_id" value="{{ old('login_id') }}" placeholder="Registered email address" autocomplete="email" maxlength="190" required autofocus>
        </div>
    </div>

    <button type="submit" id="btnSendRecovery" class="btn btn-farm-primary w-100 py-2 fw-bold mb-3 shadow-sm" style="background-color: #1B4332;">
        Send Recovery Instructions
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
        const form = document.getElementById('forgotPasswordForm');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function () {
            const button = document.getElementById('btnSendRecovery');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Sending...';
        });
    });
</script>
@endpush
