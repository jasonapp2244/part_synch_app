<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['title'] }} - {{ $siteName }}</title>
    <meta name="robots" content="{{ $published ? 'index,follow' : 'noindex' }}">
    <link rel="icon" href="{{ asset('admin/images/favicon-32x32.png') }}" type="image/png">
    <link href="{{ asset('admin/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        :root { --brand: #0d6efd; --ink: #1f2933; --muted: #5f6b7a; --line: #e4e7eb; --page: #f5f7fa; }
        body { background: var(--page); color: var(--ink); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .site-header { background: #fff; border-bottom: 1px solid var(--line); }
        .site-header .brand { font-weight: 600; color: var(--ink); text-decoration: none; }
        .doc { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 32px 28px; }
        .doc h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: .25rem; }
        .doc .updated { color: var(--muted); font-size: .9rem; margin-bottom: 1.75rem; }
        .doc-body { line-height: 1.7; font-size: 1rem; overflow-wrap: anywhere; }
        .doc-body h2 { font-size: 1.25rem; font-weight: 600; margin: 1.75rem 0 .75rem; }
        .doc-body h3 { font-size: 1.05rem; font-weight: 600; margin: 1.25rem 0 .5rem; }
        .doc-body ul, .doc-body ol { padding-left: 1.4rem; }
        .doc-body li { margin-bottom: .35rem; }
        .contact { border-top: 1px solid var(--line); margin-top: 2rem; padding-top: 1.5rem; }
        .site-footer a { color: var(--muted); text-decoration: none; margin: 0 .6rem; white-space: nowrap; }
        .site-footer a:hover, .site-footer a.active { color: var(--brand); }
        @media (max-width: 576px) { .doc { padding: 22px 16px; border-radius: 0; border-left: 0; border-right: 0; } .doc h1 { font-size: 1.45rem; } }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container py-3" style="max-width: 820px;">
            <a class="brand" href="{{ route('legal.privacy') }}">{{ $siteName }}</a>
        </div>
    </header>

    <main class="container py-4 px-0 px-sm-3" style="max-width: 820px;">
        <article class="doc">
            <h1>{{ $document['title'] }}</h1>

            @if($published && $document['updated_at'] && $document['html'] !== '')
                <div class="updated">Last updated {{ $document['updated_at']->format('F j, Y') }}</div>
            @else
                <div class="updated">&nbsp;</div>
            @endif

            @if($document['html'] !== '')
                <div class="doc-body">{!! $document['html'] !!}</div>
            @elseif(! $published)
                <p class="text-secondary">This page has not been published yet. Please check back soon.</p>
            @endif

            @if($document['key'] === 'support' || $document['key'] === 'account_deletion')
                @if($contact['email'] || $contact['phone'])
                    <div class="contact">
                        <h2 class="h5 fw-semibold">Contact us</h2>
                        @if($contact['email'])
                            <p class="mb-1">Email: <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>
                        @endif
                        @if($contact['phone'])
                            <p class="mb-1">Phone: <a href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></p>
                        @endif
                    </div>
                @elseif($document['key'] === 'support' && $document['html'] === '')
                    <p class="text-secondary">Support contact details have not been published yet.</p>
                @endif
            @endif
        </article>
    </main>

    <footer class="site-footer container pb-4 text-center small" style="max-width: 820px;">
        @foreach($pages as $key => $page)
            <a href="{{ route($page['route']) }}" class="{{ $key === $document['key'] ? 'active' : '' }}">{{ $page['title'] }}</a>
        @endforeach
        <div class="text-secondary mt-2">&copy; {{ date('Y') }} {{ $siteName }}</div>
    </footer>
</body>
</html>
