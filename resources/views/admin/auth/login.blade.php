@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <h1 class="ps-heading">Sign in</h1>
    <p class="ps-sub">Use your administrator email and password.</p>

    @include('admin.auth.partials.alerts')

    <form action="{{ route('admin.login.submit') }}" method="POST" data-busy novalidate>
        @csrf

        <div class="ps-field">
            <label class="ps-label" for="email">Email</label>
            <div class="ps-input-wrap">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="ps-input @error('email') is-invalid @enderror"
                    placeholder="admin@partsynch.com" autocomplete="username" required autofocus
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')
                <div class="ps-error" id="email-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="ps-field">
            <div class="ps-label-row">
                <label class="ps-label" for="password">Password</label>
                <a class="ps-link ps-link--small" href="{{ route('admin.password.request') }}">Forgot password?</a>
            </div>
            <div class="ps-input-wrap">
                <i class="bx bx-lock-alt" aria-hidden="true"></i>
                <input type="password" name="password" id="password"
                    class="ps-input @error('password') is-invalid @enderror"
                    placeholder="Your password" autocomplete="current-password" required
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" class="ps-reveal" data-reveal="password" aria-label="Show password" aria-pressed="false">
                    <i class="bx bx-show" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')
                <div class="ps-error" id="password-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="ps-row">
            <label class="ps-check" for="remember">
                <input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                Keep me signed in
            </label>
        </div>

        <button type="submit" class="ps-btn">
            <span class="ps-spinner" aria-hidden="true"></span>
            Sign in
        </button>
    </form>
@endsection
