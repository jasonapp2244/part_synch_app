@extends('layouts.auth')

@section('title', 'Get the Android test app')
@section('brand_note', 'Android test app')
@section('visual_title', 'Heavy-equipment parts, from vendors you can reach.')
@section('visual_text', 'Find parts by model number, message sellers and follow your orders. Vendors list products and boost them to the top of the feed.')
@section('visual_points')
    <ul class="ps-visual__points">
        <li><i class="bx bx-search-alt" aria-hidden="true"></i>Find a part by category, brand and model</li>
        <li><i class="bx bx-chat" aria-hidden="true"></i>Chat with the seller before you buy</li>
        <li><i class="bx bx-trending-up" aria-hidden="true"></i>Vendors: list products and boost them</li>
    </ul>
@endsection
@section('footer_note', 'Test build for review.')

@section('content')
    <h1 class="ps-heading">Part Synch for Android</h1>

    @if ($build)
        <p class="ps-sub">
            Test build {{ $build['version'] }}, {{ $build['size'] }}.
            @if ($build['built_at']) Built {{ $build['built_at'] }}. @endif
        </p>

        <a class="ps-btn" href="{{ route('app.download.file') }}">
            <i class="bx bx-download" aria-hidden="true"></i>
            Download APK
        </a>

        <h2 class="ps-label" style="margin-top: 28px;">How to install</h2>
        <ol class="ps-steps">
            <li>Open this page on the Android phone and tap <strong>Download APK</strong>.</li>
            <li>When the download finishes, open the file. If Android asks, allow your browser to <strong>install unknown apps</strong>.</li>
            <li>Tap <strong>Install</strong>, then open Part Synch. If an older Part Synch test app is installed, uninstall it first.</li>
        </ol>
        <p class="ps-hint">This is a test build for review, not the store version. It connects to the Part Synch test server.</p>
    @else
        <p class="ps-sub">The test build isn't available right now. Please check back soon or ask the Part Synch team for a new link.</p>
    @endif
@endsection
