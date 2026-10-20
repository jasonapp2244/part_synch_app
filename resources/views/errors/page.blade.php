{{-- Shared body for the errors/{code} views. Static text and url()/asset()
     only: these must render when the session or the database is down. --}}
@extends('layouts.auth')

@section('title', $title)

@section('content')
    <p class="ps-status"><i class="bx bx-error-circle" aria-hidden="true"></i>Error {{ $code }}</p>
    <h1 class="ps-heading">{{ $title }}</h1>
    <p class="ps-sub">{{ $message }}</p>

    <div class="ps-actions">
        @if (! empty($primary))
            <a class="ps-btn" href="{{ $primary['url'] }}">{{ $primary['label'] }}</a>
        @endif
        @if (! empty($secondary))
            <a class="ps-btn ps-btn--ghost" href="{{ $secondary['url'] }}">{{ $secondary['label'] }}</a>
        @endif
    </div>
@endsection
