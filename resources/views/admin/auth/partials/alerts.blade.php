{{-- Flash messages from AuthController, PasswordResetController and AdminMiddleware. --}}
<div aria-live="polite">
    @if (session('error'))
        <div class="ps-alert ps-alert--error" role="alert">
            <i class="bx bx-error-circle" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if (session('success'))
        <div class="ps-alert ps-alert--success" role="status">
            <i class="bx bx-check-circle" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
</div>
