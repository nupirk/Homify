<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Canonical/OG base: public marketing domain (config, not APP_URL - gateway callbacks need APP_URL). --}}
    @php($site = config('app.public_url'))
    <title>@yield('title', 'Homify — Home services in Nepal, booked in minutes')</title>
    <meta name="description" content="@yield('description', 'Book verified cleaners, plumbers and electricians across Nepal. Track the job live and pay after the service.')">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="@yield('canonical', $site . '/')">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Homify">
    <meta property="og:title" content="@yield('title', 'Homify — Home services in Nepal, booked in minutes')">
    <meta property="og:description" content="@yield('description', 'Book verified cleaners, plumbers and electricians across Nepal. Track the job live and pay after the service.')">
    <meta property="og:url" content="@yield('canonical', $site . '/')">
    <meta property="og:image" content="{{ $site }}/img/og.png">
    <meta property="og:locale" content="en_NP">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'Homify — Home services in Nepal')">
    <meta name="twitter:description" content="@yield('description', 'Book trusted cleaners, plumbers and electricians across Nepal. Pay after service.')">
    <meta name="twitter:image" content="{{ $site }}/img/og.png">

    {{-- App / brand --}}
    <meta name="theme-color" content="#4F46E5">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">

    <link rel="preload" as="font" type="font/woff2" href="/fonts/bevietnampro-400.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/fonts/bevietnampro-800.woff2" crossorigin>
    <link rel="stylesheet" href="/css/site.css">

    @yield('head')
</head>
<body>
    <header class="site-header" id="siteHeader">
        <div class="container">
            <a class="brand" href="/">
                <span class="brand-mark" aria-hidden="true">H</span>
                Homify
            </a>
            <nav class="site-nav" aria-label="Main">
                <a href="/#services">Services</a>
                <a href="/#how">How it works</a>
                <a href="/#partners">For partners</a>
                <a href="/#faq">FAQ</a>
            </nav>
            <a class="btn btn-primary header-cta" href="/#download">Get the app</a>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <a class="brand" href="/">
                        <span class="brand-mark" aria-hidden="true">H</span>
                        Homify
                    </a>
                    <p>Home services, done right. Verified professionals across Nepal.</p>
                </div>
                <div class="footer-links">
                    <section>
                        <h4>Product</h4>
                        <a href="/#services">Services</a>
                        <a href="/#how">How it works</a>
                        <a href="/#download">Get the app</a>
                    </section>
                    <section>
                        <h4>Partners</h4>
                        <a href="/#partners">Earn with Homify</a>
                        <a href="mailto:support@homify.com.np?subject=Partner%20application">Apply as a partner</a>
                    </section>
                    <section>
                        <h4>Legal</h4>
                        <a href="/privacy">Privacy Policy</a>
                        <a href="/terms">Terms of Service</a>
                    </section>
                    <section>
                        <h4>Support</h4>
                        <a href="mailto:support@homify.com.np">support@homify.com.np</a>
                    </section>
                </div>
            </div>
            <div class="copyright">
                <span>&copy; {{ date('Y') }} Homify Services Pvt. Ltd. Kathmandu, Nepal</span>
                <span>Made in Nepal</span>
            </div>
        </div>
    </footer>

    <script>
        // Sticky header hairline after scroll (rAF-throttled).
        (function () {
            var header = document.getElementById('siteHeader');
            var ticking = false;
            function update() {
                header.classList.toggle('scrolled', window.scrollY > 8);
                ticking = false;
            }
            window.addEventListener('scroll', function () {
                if (!ticking) { requestAnimationFrame(update); ticking = true; }
            }, { passive: true });
            update();
        })();

        // Scroll reveals (skipped entirely under prefers-reduced-motion by CSS).
        (function () {
            var els = document.querySelectorAll('.reveal');
            if (!('IntersectionObserver' in window) || !els.length) {
                els.forEach(function (el) { el.classList.add('in'); });
                return;
            }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
                });
            }, { threshold: 0.15 });
            els.forEach(function (el) { io.observe(el); });
        })();
    </script>
    @yield('scripts')
</body>
</html>
