@include('errors.page', [
    'code' => 404,
    'title' => "This page doesn't exist",
    'message' => 'The link may be out of date, or the page was moved. Check the address, or head back to the dashboard.',
    'primary' => ['url' => url('/dashboard'), 'label' => 'Go to dashboard'],
    'secondary' => ['url' => url('/login'), 'label' => 'Sign in'],
])
