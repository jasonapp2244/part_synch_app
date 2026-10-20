@extends('layouts.auth')

@section('title', 'Reset your password')

@section('content')
    <h1 class="ps-heading">Reset your password</h1>
    <p class="ps-sub">Enter your administrator email. We'll send you a 6-digit code to set a new password.</p>

    @include('admin.auth.partials.alerts')

    <form action="{{ route('admin.password.email') }}" method="POST" data-busy novalidate>
        @csrf

        <div class="ps-field">
            <label class="ps-label" for="email">Email</label>
            <div class="ps-input-wrap">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="ps-input @error('email') is-invalid @enderror"
                    placeholder="admin@partsynch.com" autocomplete="email" required autofocus
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')
                <div class="ps-error" id="email-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="ps-btn">
            <span class="ps-spinner" aria-hidden="true"></span>
            Send code
        </button>
    </form>

    <p class="ps-alt">Remembered it? <a class="ps-link" href="{{ route('admin.login') }}">Back to sign in</a></p>
@endsection
