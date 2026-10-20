<!doctype html>
<html lang="en">

<head>
    {{-- Sign-in, password reset and error pages. Self-contained: no session or
         database use here, so the 500 and 503 pages can render with both down. --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | Part Synch Admin</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ asset('admin/images/brand/favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('admin/images/brand/favicon-180.png') }}">
    <link rel="preload" as="image" href="{{ asset('admin/images/brand/login-side.jpg') }}">
    <link rel="stylesheet" href="{{ asset('admin/css/icons.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/css/auth.css') }}">
</head>

<body class="ps-auth">
    <div class="ps-shell">
        <aside class="ps-visual" aria-label="Part Synch">
            <img class="ps-visual__photo" src="{{ asset('admin/images/brand/login-side.jpg') }}" alt="" width="440" height="956">
            <div class="ps-visual__inner">
                <span class="ps-brand">
                    <span class="ps-brand__mark"><img src="{{ asset('admin/images/brand/logo.png') }}" alt="Part Synch logo" width="215" height="195"></span>
                    <span class="ps-brand__name">Part Synch<small>@yield('brand_note', 'Admin panel')</small></span>
                </span>

                <div class="ps-visual__copy">
                    <h2 class="ps-visual__title">@yield('visual_title', 'Run the marketplace that keeps job sites moving.')</h2>
                    <p class="ps-visual__text">@yield('visual_text', 'Approve vendors, follow orders and schedule boosts for heavy-equipment parts, all in one place.')</p>
                    @hasSection('visual_points')
                        @yield('visual_points')
                    @else
                    <ul class="ps-visual__points">
                        <li><i class="bx bx-store-alt" aria-hidden="true"></i>Vendors, products and categories</li>
                        <li><i class="bx bx-package" aria-hidden="true"></i>Orders, earnings and invoices</li>
                        <li><i class="bx bx-trending-up" aria-hidden="true"></i>Boost requests and card payments</li>
                    </ul>
                    @endif
                </div>
            </div>
        </aside>

        <main class="ps-panel">
            <div class="ps-panel__body">
                <div class="ps-card">
                    <div class="ps-card__logo">
                        <img src="{{ asset('admin/images/brand/logo.png') }}" alt="" width="215" height="195">
                        <span><strong>Part Synch</strong>@yield('brand_note', 'Admin panel')</span>
                    </div>
                    @yield('content')
                </div>
            </div>
            <footer class="ps-panel__footer">&copy; {{ date('Y') }} Part Synch. @yield('footer_note', 'Administrator access only.')</footer>
        </main>
    </div>

    <script>
        // Show / hide password, and a busy state on submit so a slow request
        // is not sent twice.
        document.querySelectorAll('[data-reveal]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-reveal'));
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.querySelector('i').className = show ? 'bx bx-hide' : 'bx bx-show';
            });
        });
        document.querySelectorAll('form[data-busy]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var button = form.querySelector('button[type="submit"]');
                if (!button) return;
                button.disabled = true;
                button.classList.add('is-busy');
            });
        });
        // Coming back with the browser's Back button restores the page from
        // cache with the button still busy; release it.
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form[data-busy] button[type="submit"]').forEach(function (button) {
                button.disabled = false;
                button.classList.remove('is-busy');
            });
        });
        // The 6-digit code field takes digits only, including when pasted.
        var code = document.getElementById('code');
        if (code) {
            code.addEventListener('input', function () {
                code.value = code.value.replace(/\D/g, '').slice(0, 6);
            });
        }
    </script>
</body>

</html>
