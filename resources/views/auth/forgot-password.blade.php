@extends('layouts.auth')

@section('title', 'Forgot Password - FarmReach Cloud')

@section('content')
<div class="text-center mb-3">
    <h5 class="fw-bold">Password Recovery</h5>
    <p class="text-muted small">Enter the Philippine mobile number registered to your FarmReach account. We will send a one-time verification code by SMS.</p>
</div>

<form id="forgotPasswordForm" action="{{ route('password.email') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label for="phone_number" class="form-label fw-semibold small text-dark">Registered Mobile Number</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-phone text-muted"></i></span>
            <input type="tel" class="form-control border-start-0 @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" placeholder="09XXXXXXXXX" autocomplete="tel" maxlength="30" inputmode="tel" required autofocus>
        </div>
        @error('phone_number')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" id="btnSendRecovery" class="btn btn-farm-primary w-100 py-2 fw-bold mb-3 shadow-sm" style="background-color: #1B4332;">
        Send SMS Verification Code
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
