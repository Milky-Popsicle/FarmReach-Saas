@extends('layouts.auth')

@section('title', 'Create Account - FarmReach Cloud')

@section('content')
<form action="{{ route('register.submit') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label for="name" class="form-label fw-semibold small text-dark">Full Name</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
            <input type="text" class="form-control border-start-0" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. Juan Dela Cruz" required>
        </div>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label fw-semibold small text-dark">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" class="form-control border-start-0" id="email" name="email" value="{{ old('email') }}" placeholder="name@example.com" required>
        </div>
    </div>

    <div class="mb-3">
        <label for="phone_number" class="form-label fw-semibold small text-dark">Phone Number</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-telephone text-muted"></i></span>
            <input type="text" class="form-control border-start-0" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" placeholder="09xxxxxxxxx" required>
        </div>
    </div>

    <div class="mb-3">
        <label for="role" class="form-label fw-semibold small text-dark">Select Role</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person-badge text-muted"></i></span>
            <select class="form-select border-start-0" id="role" name="role" required>
                <option value="buyer" {{ old('role') == 'buyer' ? 'selected' : '' }}>Buyer (Household or Business Customer)</option>
                <option value="farmer" {{ old('role') == 'farmer' ? 'selected' : '' }}>Farmer (Grower / Crop Producer)</option>
                @if(!$adminExists)
                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Administrator (System Owner)</option>
                @else
                    <option value="" disabled>Administrator (Already registered - limit 1)</option>
                @endif
            </select>
        </div>
        <small class="text-muted d-block mt-1">
            <i class="bi bi-info-circle"></i> In FarmReach, Administrator accounts are restricted to single ownership.
        </small>
    </div>

    <div class="mb-3">
        <label for="address" class="form-label fw-semibold small text-dark">Farm or Delivery Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-geo-alt text-muted"></i></span>
            <input type="text" class="form-control border-start-0" id="address" name="address" value="{{ old('address') }}" placeholder="e.g. Brgy. Cagdianao, Surigao City" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="password" class="form-label fw-semibold small text-dark">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Min. 6 chars" required>
        </div>
        <div class="col-md-6 mb-3">
            <label for="password_confirmation" class="form-label fw-semibold small text-dark">Confirm</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Repeat" required>
        </div>
    </div>

    <button type="submit" class="btn btn-farm-primary w-100 py-2 fw-bold mb-3 shadow-sm" style="background-color: #1B4332;">
        <i class="bi bi-person-plus me-1"></i> Create Account
    </button>

    <div class="text-center">
        <span class="small text-muted">Already registered?</span>
        <a href="{{ route('login') }}" class="small fw-bold text-decoration-none text-success">Sign In</a>
    </div>
</form>
@endsection
