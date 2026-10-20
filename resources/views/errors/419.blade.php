@include('errors.page', [
    'code' => 419,
    'title' => 'Your session expired',
    'message' => 'For security, forms stop working after a while. Sign in again, then repeat what you were doing.',
    'primary' => ['url' => url('/login'), 'label' => 'Sign in again'],
])
