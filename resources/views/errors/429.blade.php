@include('errors.page', [
    'code' => 429,
    'title' => 'Too many attempts',
    'message' => 'To protect administrator accounts, sign-in and password reset are paused for a minute. Wait a moment, then try again.',
    'primary' => ['url' => url('/login'), 'label' => 'Back to sign in'],
])
