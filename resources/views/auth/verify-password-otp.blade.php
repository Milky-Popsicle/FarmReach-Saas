@extends('layouts.auth')

@section('title', 'Verify Recovery Code - FarmReach Cloud')

@section('content')
<div class="text-center mb-3">
    <h5 class="fw-bold">Verify Recovery Code</h5>
    <p class="text-muted small">
        Enter the 6-digit code sent to your registered mobile number
        <strong>{{ $maskedPhone }}</strong>.
        The code expires in 5 minutes.
    </p>
</div>

<form id="verifyRecoveryCodeForm" action="{{ route('password.sms.verify.submit') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label for="code" class="form-label fw-semibold small text-dark">6-Digit Verification Code</label>
        <input
            type="text"
            class="form-control text-center fw-bold @error('code') is-invalid @enderror"
            id="code"
            name="code"
            value="{{ old('code') }}"
            placeholder="000000"
            inputmode="numeric"
            autocomplete="one-time-code"
            pattern="[0-9]{6}"
            maxlength="6"
            minlength="6"
            required
            autofocus
        >
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" id="btnVerifyRecoveryCode" class="btn btn-farm-primary w-100 py-2 fw-bold mb-3 shadow-sm" style="background-color: #1B4332;">
        Verify Code
    </button>
</form>

<form action="{{ route('password.sms.resend') }}" method="POST" class="text-center mb-3">
    @csrf
    <button type="submit" class="btn btn-link small fw-semibold text-decoration-none">
        Resend code
    </button>
</form>

<div class="text-center">
    <a href="{{ route('password.request') }}" class="small fw-bold text-decoration-none text-muted">
        <i class="bi bi-arrow-left me-1"></i> Use a different mobile number
    </a>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('verifyRecoveryCodeForm');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function () {
            const button = document.getElementById('btnVerifyRecoveryCode');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Verifying...';
        });
    });
</script>
@endpush
