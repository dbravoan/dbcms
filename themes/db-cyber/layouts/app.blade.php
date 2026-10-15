@php
    // Resolve the page title/description from the rendered sections (or explicit
    // variables) so the unified seo-head always emits a complete head.
    $pageTitle = $pageTitle ?? (trim(strip_tags((string) view()->yieldContent('title'))) ?: null);
    $pageDescription = $pageDescription ?? (trim(strip_tags((string) view()->yieldContent('meta_description'))) ?: null);
@endphp
<!DOCTYPE html>
@php
    $__dir = app(\App\Contracts\Services\LocalizationServiceInterface::class)->activeLanguages()
        ->firstWhere("code", app()->getLocale())?->direction ?? "ltr";
@endphp
<html lang="{{ app()->getLocale() }}" dir="{{ $__dir }}" class="h-full front-db-cyber theme-{{ $adminTheme ?? 'dbcms' }} bg-[#1A1A1A]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
     <title>{{ $seoMeta?->title ?? $pageTitle ?? config('cms.name') }} | {{ config('cms.name') }}</title>

    @include('themes.components.seo-head')
    @vite(cms_asset_entries(true))
</head>
<body class="min-h-full flex flex-col justify-between relative selection:bg-[#00FF99] selection:text-black">

    <!-- Ambient Generative Polygon Canvas -->
    <canvas id="polygon-canvas"></canvas>

    <!-- Header Navigation -->
    <div class="contents">
    <!--cms-private:themes.db-cyber.partials.header:start-->
        @include('themes.db-cyber.partials.header')
    <!--cms-private:end-->
    </div>

    <!-- Main Page Content Slot -->
    <main class="flex-grow relative z-10">
        @yield('content')
    </main>

    <!-- Footer -->
    <div class="relative z-20">
        @include('themes.db-cyber.partials.footer')
    </div>

    <!-- First-Time Visitor Geolocation Preference Modal -->
    <!--cms-private:themes.components.visitor-geo-modal:start-->
    @include('themes.components.visitor-geo-modal')
    <!--cms-private:end-->

    <!-- Dynamic GDPR Cookie Consent Banner & Modal -->
    <!--cms-private:themes.components.cookie-consent-modal:start-->
    @include('themes.components.cookie-consent-modal')
    <!--cms-private:end-->

    <!-- Frontoffice Admin Bar & Real-Time SEO/GEO Quality Inspector -->
    @include('components.frontoffice-admin-bar')

    <!-- Google Analytics 4 (GDPR Consent Gated) -->
    <!--cms-private:themes.components.analytics:start-->
    @include('themes.components.analytics')
    <!--cms-private:end-->

    <!-- Generative Polygon Canvas & Scroll-Magic Scripts -->
    <script nonce="{{ $cspNonce ?? '' }}">
        // Polygon generative canvas
        (function() {
            var canvas = document.getElementById('polygon-canvas');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');

            function resize() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resize);
            resize();

            var currentPolygon = {
                size: Math.min(window.innerWidth, window.innerHeight) * 0.38,
                numberOfVertices: 3,
                line: { color: 'rgba(0, 255, 153, 0.4)', width: 1.5 },
                centerPosition: { x: window.innerWidth / 2, y: window.innerHeight / 2 }
            };

            var fps = 6, fpsInterval = 1000 / fps, then = Date.now(), addVertex = true;

            // Respect accessibility preferences and stop burning CPU when hidden.
            var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var rafId = null;

            function drawPolygon() {
                var now = Date.now(), elapsed = now - then;
                if (elapsed > fpsInterval) {
                    then = now - (elapsed % fpsInterval);
                    ctx.clearRect(0, 0, canvas.width, canvas.height);

                    if (currentPolygon.numberOfVertices >= 80 && addVertex) addVertex = false;
                    if (currentPolygon.numberOfVertices <= 3 && !addVertex) addVertex = true;

                    // Draw Polygon
                    var nV = currentPolygon.numberOfVertices;
                    var rotate = (Math.PI - (2 * Math.PI) / nV) / 2;
                    var size = currentPolygon.size;
                    var cx = canvas.width / 2, cy = canvas.height / 2;

                    ctx.strokeStyle = currentPolygon.line.color;
                    ctx.lineWidth = currentPolygon.line.width;
                    ctx.beginPath();
                    ctx.moveTo(cx + size * Math.cos(rotate), cy + size * Math.sin(rotate));
                    for (var i = 1; i <= nV; i++) {
                        var sx = cx + size * Math.cos(rotate + (i * 2 * Math.PI / nV));
                        var sy = cy + size * Math.sin(rotate + (i * 2 * Math.PI / nV));
                        ctx.lineTo(sx, sy);
                    }
                    ctx.closePath();
                    ctx.stroke();

                    if (addVertex) currentPolygon.numberOfVertices += 1;
                    else currentPolygon.numberOfVertices -= 1;
                    fps = Math.max(4, Math.min(24, currentPolygon.numberOfVertices));
                    fpsInterval = 1000 / fps;
                }
                rafId = window.requestAnimationFrame(drawPolygon);
            }

            function stopCanvas() {
                if (rafId === null) return;
                window.cancelAnimationFrame(rafId);
                rafId = null;
            }

            function startCanvas() {
                if (rafId !== null) return;
                then = Date.now();
                rafId = window.requestAnimationFrame(drawPolygon);
            }

            // Render one static frame when the visitor prefers reduced motion.
            if (reduceMotion) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.strokeStyle = currentPolygon.line.color;
                ctx.lineWidth = currentPolygon.line.width;
                ctx.beginPath();
                var r = currentPolygon.size;
                for (var i = 0; i <= 40; i++) {
                    var a = (i * 2 * Math.PI) / 40;
                    var x = canvas.width / 2 + r * Math.cos(a);
                    var y = canvas.height / 2 + r * Math.sin(a);
                    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.closePath();
                ctx.stroke();
            } else {
                startCanvas();
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        stopCanvas();
                    } else {
                        startCanvas();
                    }
                });
            }
        })();
    </script>

    <!-- Scroll-Magic & Interactive Scrub Engine -->
    @include('themes.components.scroll-magic')

    @stack('admin_scripts')
    @stack('scripts')
</body>
</html>
