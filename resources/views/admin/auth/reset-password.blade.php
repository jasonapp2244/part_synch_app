@extends('layouts.auth')

@section('title', 'Set a new password')

@section('content')
    <h1 class="ps-heading">Set a new password</h1>
    <p class="ps-sub">Enter the 6-digit code from your email, then choose a new password.</p>

    @include('admin.auth.partials.alerts')

    <form action="{{ route('admin.password.update') }}" method="POST" data-busy novalidate>
        @csrf

        <div class="ps-field">
            <label class="ps-label" for="email">Email</label>
            <div class="ps-input-wrap">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <input type="email" name="email" id="email" value="{{ old('email', $email) }}"
                    class="ps-input @error('email') is-invalid @enderror"
                    placeholder="admin@partsynch.com" autocomplete="username" required
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')
                <div class="ps-error" id="email-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="ps-field">
            <label class="ps-label" for="code">6-digit code</label>
            <div class="ps-input-wrap">
                <i class="bx bx-key" aria-hidden="true"></i>
                <input type="text" name="code" id="code" value="{{ old('code') }}"
                    class="ps-input ps-input--code @error('code') is-invalid @enderror"
                    inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                    placeholder="000000" required @if ($email !== '') autofocus @endif
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
            </div>
            @error('code')
                <div class="ps-error" id="code-error">{{ $message }}</div>
            @else
                <div class="ps-hint">The code expires 15 minutes after it was sent.</div>
            @enderror
        </div>

        <div class="ps-field">
            <label class="ps-label" for="password">New password</label>
            <div class="ps-input-wrap">
                <i class="bx bx-lock-alt" aria-hidden="true"></i>
                <input type="password" name="password" id="password"
                    class="ps-input @error('password') is-invalid @enderror"
                    placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" class="ps-reveal" data-reveal="password" aria-label="Show password" aria-pressed="false">
                    <i class="bx bx-show" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')
                <div class="ps-error" id="password-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="ps-field">
            <label class="ps-label" for="password_confirmation">Confirm new password</label>
            <div class="ps-input-wrap">
                <i class="bx bx-lock-alt" aria-hidden="true"></i>
                <input type="password" name="password_confirmation" id="password_confirmation"
                    class="ps-input" placeholder="Type it again" autocomplete="new-password" minlength="8" required>
                <button type="button" class="ps-reveal" data-reveal="password_confirmation" aria-label="Show password" aria-pressed="false">
                    <i class="bx bx-show" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="ps-btn">
            <span class="ps-spinner" aria-hidden="true"></span>
            Update password
        </button>
    </form>

    <p class="ps-alt">Didn't get a code? <a class="ps-link" href="{{ route('admin.password.request') }}">Send a new one</a></p>
@endsection
