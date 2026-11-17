@extends('layouts.auth')

@section('title', 'Reset Password - FarmReach Cloud')

@section('content')
<div class="text-center mb-3">
    <h5 class="fw-bold">Create a New Password</h5>
    <p class="text-muted small">Your mobile number has been verified. Choose a new password for your FarmReach account.</p>
</div>

<form action="{{ route('password.sms.reset.submit') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="password" class="form-label fw-semibold small text-dark">New Password</label>
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" minlength="8" required autofocus>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Use at least 8 characters.</div>
    </div>
    <div class="mb-3">
        <label for="password_confirmation" class="form-label fw-semibold small text-dark">Confirm New Password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required>
    </div>
    <button type="submit" class="btn btn-farm-primary w-100 py-2 fw-bold" style="background-color: #1B4332;">Reset Password</button>
</form>
@endsection
