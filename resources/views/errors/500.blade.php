@include('errors.page', [
    'code' => 500,
    'title' => 'Something went wrong on our side',
    'message' => 'The server could not finish this request. Try again in a moment; if it keeps happening, contact the Part Synch technical team.',
    'primary' => ['url' => url('/dashboard'), 'label' => 'Go to dashboard'],
])
