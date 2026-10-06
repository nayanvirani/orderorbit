{{-- Standalone error page: no database, menus or app code, so it renders even when something is down. --}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · OrderOrbit Space</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    @include('site.partials.theme')
    <style>
        :root { --ink: var(--c-text); --paper: var(--c-bg); --muted: var(--c-muted); --line: var(--c-line); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { display: grid; min-height: 100vh; place-items: center; padding: 32px 16px; background: var(--paper); color: var(--ink); font: var(--fs-body)/1.55 var(--f-body), system-ui, sans-serif; }
        main { width: min(600px, 100%); text-align: center; }
        .logo { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 48px; color: var(--ink); text-decoration: none; font: var(--fw-heading) 22px/1 var(--f-heading), system-ui, sans-serif; }
        .orbit { position: relative; width: 112px; height: 112px; margin: 0 auto 26px; }
        .orbit span { position: absolute; inset: 0; border: 1px dashed var(--c-line-strong); border-radius: 50%; }
        .orbit b { position: absolute; top: 50%; left: 50%; width: 26px; height: 26px; margin: -13px 0 0 -13px; border-radius: 50%; background: var(--ink); }
        .orbit i { position: absolute; top: 6px; right: -10px; width: 12px; height: 12px; border-radius: 50%; background: var(--ink); animation: drift 6s ease-in-out infinite alternate; }
        @keyframes drift { to { transform: translate(16px, -10px); } }
        @media (prefers-reduced-motion: reduce) { .orbit i { animation: none; } }
        .code { font: 500 12px/1 var(--f-label), system-ui, sans-serif; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 14px 0 14px; color: var(--c-heading); font: var(--fw-heading) clamp(40px, 9vw, 64px)/1.02 var(--f-heading), system-ui, sans-serif; letter-spacing: -.01em; text-wrap: balance; }
        h1 em { font-style: normal; text-decoration: underline; text-decoration-thickness: .05em; text-underline-offset: .12em; }
        p { margin: 0 auto; max-width: 460px; color: var(--muted); text-wrap: pretty; }
        .ctas { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 30px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 22px; border: 1px solid var(--ink); border-radius: var(--radius-btn); color: var(--ink); font: 500 15px/1 var(--f-body), sans-serif; text-decoration: none; background: none; cursor: pointer; }
        .btn.primary { background: var(--c-accent); color: var(--c-on-accent); border-color: var(--c-accent); }
        .btn:focus-visible { outline: 2px solid var(--ink); outline-offset: 3px; }
        @media (max-width: 480px) { .ctas .btn { flex: 1 1 100%; } .logo { margin-bottom: 36px; } }
    </style>
</head>
<body>
    <main>
        <a class="logo" href="/"><img src="/brand/orderorbit-logo.svg" alt="OrderOrbit Space" width="190" height="34"></a>
        <div class="orbit" aria-hidden="true"><span></span><b></b><i></i></div>
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="ctas">@yield('actions')</div>
    </main>
</body>
</html>
