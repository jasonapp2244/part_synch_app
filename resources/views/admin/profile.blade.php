@extends('layouts.admin')

@section('title', 'My Profile')

@section('content')
    @php
        $passwordTab = request('tab') === 'password'
            || $errors->has('current_password')
            || $errors->has('password');
        $fullName = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')) ?: 'Admin';
    @endphp

    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">My Profile</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">My Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center py-4">
                            <img src="{{ $admin->avatarUrl() }}" id="avatar-preview" class="rounded-circle mb-3 border"
                                width="120" height="120" style="object-fit: cover;" alt="Profile photo">

                            <form method="POST" action="{{ route('admin.profile.photo') }}" enctype="multipart/form-data" class="mb-2">
                                @csrf
                                <label for="photo" class="btn btn-sm btn-outline-primary mb-0">
                                    <i class="bx bx-camera me-1"></i>{{ $admin->profile_image ? 'Change photo' : 'Upload photo' }}
                                </label>
                                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="d-none">
                                <div id="photo-actions" class="mt-2 d-none">
                                    <div class="small text-muted mb-2" id="photo-name"></div>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bx bx-upload me-1"></i>Save photo</button>
                                    <button type="button" class="btn btn-sm btn-light" id="photo-cancel">Cancel</button>
                                </div>
                                @error('photo')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                <div class="form-text">JPG, PNG or WebP, up to 2 MB.</div>
                            </form>

                            @if($admin->profile_image)
                                <form method="POST" action="{{ route('admin.profile.photo.destroy') }}" class="mb-3"
                                    onsubmit="return confirm('Remove your profile photo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">Remove photo</button>
                                </form>
                            @endif

                            <h5 class="mb-1">{{ $fullName }}</h5>
                            <p class="text-muted mb-1">{{ $admin->email }}</p>
                            @if($admin->phone_number)
                                <p class="text-muted mb-1">{{ $admin->phone_number }}</p>
                            @endif
                            <span class="badge bg-primary">Administrator</span>
                            <hr>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="bx bx-log-out-circle me-1"></i>Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $passwordTab ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button" role="tab">
                                        <i class="bx bx-user me-1"></i>Profile
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $passwordTab ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-password" type="button" role="tab">
                                        <i class="bx bx-lock-alt me-1"></i>Change Password
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body tab-content">
                            <div class="tab-pane fade {{ $passwordTab ? '' : 'show active' }}" id="tab-profile" role="tabpanel">
                                <form method="POST" action="{{ route('admin.profile.update') }}">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="first_name">First name</label>
                                            <input type="text" id="first_name" name="first_name" required maxlength="150"
                                                class="form-control @error('first_name') is-invalid @enderror"
                                                value="{{ old('first_name', $admin->first_name) }}">
                                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="last_name">Last name</label>
                                            <input type="text" id="last_name" name="last_name" maxlength="150"
                                                class="form-control @error('last_name') is-invalid @enderror"
                                                value="{{ old('last_name', $admin->last_name) }}">
                                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="email">Email (used to sign in)</label>
                                            <input type="email" id="email" name="email" required maxlength="255"
                                                class="form-control @error('email') is-invalid @enderror"
                                                value="{{ old('email', $admin->email) }}">
                                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="phone_number">Phone</label>
                                            <input type="text" id="phone_number" name="phone_number" maxlength="50"
                                                class="form-control @error('phone_number') is-invalid @enderror"
                                                value="{{ old('phone_number', $admin->phone_number) }}">
                                            @error('phone_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Save profile</button>
                                </form>
                            </div>

                            <div class="tab-pane fade {{ $passwordTab ? 'show active' : '' }}" id="tab-password" role="tabpanel">
                                <form method="POST" action="{{ route('admin.profile.password') }}" autocomplete="off">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label" for="current_password">Current password</label>
                                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                                            class="form-control @error('current_password') is-invalid @enderror">
                                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="password">New password</label>
                                        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                                            class="form-control @error('password') is-invalid @enderror">
                                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">At least 8 characters.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                                        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                            class="form-control">
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="bx bx-lock-alt me-1"></i>Change password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    (function () {
        var input = document.getElementById('photo');
        var preview = document.getElementById('avatar-preview');
        var actions = document.getElementById('photo-actions');
        var original = preview.src;
        var objectUrl = null;

        function reset() {
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
            input.value = '';
            preview.src = original;
            actions.classList.add('d-none');
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { reset(); return; }
            if (objectUrl) { URL.revokeObjectURL(objectUrl); }
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            document.getElementById('photo-name').textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
            actions.classList.remove('d-none');
        });

        document.getElementById('photo-cancel').addEventListener('click', reset);
    })();
</script>
@endsection
