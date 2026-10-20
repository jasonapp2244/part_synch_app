@include('errors.page', [
    'code' => 403,
    'title' => "You don't have access to this page",
    'message' => 'This area is for Part Synch administrators. Sign in with an administrator account to continue.',
    'primary' => ['url' => url('/dashboard'), 'label' => 'Go to dashboard'],
    'secondary' => ['url' => url('/login'), 'label' => 'Sign in with another account'],
])
