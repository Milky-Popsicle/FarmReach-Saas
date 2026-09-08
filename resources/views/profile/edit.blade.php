@extends(Auth::user() && Auth::user()->isAdmin() ? 'layouts.admin' : 'layouts.app')

@section('title', 'My Profile - FarmReach Cloud')
@section('header_title', 'Admin My Profile')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="farm-card p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                    <div>
                        <h2 class="fw-bold mb-1">Edit your Profile</h2>
                        <span class="badge bg-farm-primary text-white text-uppercase px-3 py-1">
                            {{ $user->role }} Account
                        </span>
                    </div>
                    @if($user->isFarmer())
                        <a href="{{ route('farmer.products') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-box-seam me-1"></i> My Products
                        </a>
                    @elseif($user->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    @else
                        <a href="{{ route('buyer.orders') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-bag-check me-1"></i> My Orders
                        </a>
                    @endif
                </div>

                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-4">
                        <!-- Profile Form Fields -->
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-bold small">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label fw-bold small">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" {{ old('gender', $user->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ old('gender', $user->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                                        <option value="Other" {{ old('gender', $user->gender) == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="birthday" class="form-label fw-bold small">Birthday</label>
                                    <input type="date" class="form-control" id="birthday" name="birthday" value="{{ old('birthday', $user->birthday?->format('Y-m-d')) }}">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label fw-bold small">
                                    @if($user->isFarmer())
                                        Registered Farm / Business Location
                                    @else
                                        Delivery / Residential Address
                                    @endif
                                </label>
                                <input type="text" class="form-control" id="address" name="address" value="{{ old('address', $user->address) }}" placeholder="e.g. Brgy. Cagdianao, Surigao City" required>
                                @if($user->isFarmer())
                                    <small class="text-muted"><i class="bi bi-info-circle"></i> This address is automatically displayed on your marketplace crop listings so buyers can locate local produce.</small>
                                @endif
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label fw-bold small">Phone Number</label>
                                    <input type="text" class="form-control" id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label fw-bold small">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="bio" class="form-label fw-bold small">
                                    @if($user->isFarmer()) My Farm Story / Bio @else Short Bio @endif
                                </label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Tell consumers or community members about yourself or your farm practices...">{{ old('bio', $user->bio) }}</textarea>
                            </div>

                            <hr class="my-4">

                            <h5 class="fw-bold mb-3 text-farm-primary">Change Password (Leave blank to keep current)</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label fw-bold small">New Password</label>
                                    <div class="input-group flex-nowrap">
                                        <input type="password" class="form-control border-end-0" id="password" name="password" placeholder="New password" autocomplete="new-password">
                                        <button type="button" class="btn btn-toggle-password" data-target="#password" onclick="togglePasswordVisibility(this, event)" title="Show Password" aria-label="Show Password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirmation" class="form-label fw-bold small">Confirm Password</label>
                                    <div class="input-group flex-nowrap">
                                        <input type="password" class="form-control border-end-0" id="password_confirmation" name="password_confirmation" placeholder="Repeat new password" autocomplete="new-password">
                                        <button type="button" class="btn btn-toggle-password" data-target="#password_confirmation" onclick="togglePasswordVisibility(this, event)" title="Show Password" aria-label="Show Password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Avatar and Photo Box (Matching wireframe) -->
                        <div class="col-md-4 text-center">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="mb-3" id="avatarPreviewContainer">
                                    @if($user->avatar_path)
                                        <img src="{{ $user->avatar_path }}" alt="Avatar" class="rounded-circle img-thumbnail shadow-sm" style="width: 140px; height: 140px; object-fit: cover;">
                                    @else
                                        <div class="rounded-circle bg-farm-primary text-warning d-inline-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 140px; height: 140px; font-size: 50px;">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>

                                <label for="avatar" class="btn btn-sm btn-farm-primary w-100 mb-2">
                                    <i class="bi bi-camera me-1"></i> Upload Photo
                                </label>
                                <input type="file" class="d-none" id="avatar" name="avatar" accept="image/*" onchange="previewAvatar(this)">
                                <small class="text-muted d-block">PNG, JPG up to 2MB</small>
                            </div>

                            <button type="submit" class="btn btn-farm-accent w-100 mt-4 py-2 text-dark fw-bold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const container = document.getElementById('avatarPreviewContainer');
                if (container) {
                    container.innerHTML = '<img src="' + e.target.result + '" alt="Avatar Preview" class="rounded-circle img-thumbnail shadow-sm" style="width: 140px; height: 140px; object-fit: cover;">';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection
