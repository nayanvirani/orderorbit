<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>OrderOrbit Space · Coming soon</title>
    <meta name="description" content="OrderOrbit Space builds technology for online commerce. A new website is on its way.">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/brand/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
    @include('site.partials.theme')
    <style>
        :root { --ink: var(--c-text); --paper: var(--c-bg); --muted: var(--c-muted); --line: var(--c-line); }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { display: grid; min-height: 100vh; place-items: center; padding: 24px 16px; background: var(--paper); color: var(--ink); font: var(--fs-body)/1.55 var(--f-body), system-ui, sans-serif; overflow-x: hidden; }
        main { position: relative; width: min(560px, 100%); text-align: center; }
        .orbit { position: relative; width: 132px; height: 132px; margin: 0 auto 28px; }
        .orbit span { position: absolute; inset: 0; border: 1px solid var(--line); border-radius: 50%; }
        .orbit span:nth-child(2) { inset: 22px; border-color: var(--c-line-strong); }
        .orbit b { position: absolute; top: 50%; left: 50%; width: 30px; height: 30px; margin: -15px 0 0 -15px; border-radius: 50%; background: var(--ink); }
        .orbit i { position: absolute; top: -5px; left: 50%; width: 10px; height: 10px; margin-left: -5px; border-radius: 50%; background: var(--ink); transform-origin: 5px 71px; animation: spin 9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .orbit i { animation: none; } }
        .eyebrow { font: 500 11px/1 var(--f-label), system-ui, sans-serif; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        h1 { margin: 14px 0 12px; color: var(--c-heading); font: var(--fw-heading) clamp(40px, 9vw, 64px)/1 var(--f-heading), system-ui, sans-serif; letter-spacing: -.01em; }
        p { margin: 0 auto; max-width: 440px; color: var(--muted); }
        .product { display: flex; align-items: center; gap: 14px; margin: 36px auto 0; max-width: 420px; padding: 16px 18px; border: 1px solid var(--line); border-radius: var(--radius-card, 14px); background: var(--c-surface, var(--paper)); color: var(--ink); text-align: left; text-decoration: none; transition: border-color .15s; }
        .product:hover { border-color: var(--c-accent); }
        .product svg { flex: none; width: 40px; height: 40px; color: var(--c-accent); }
        .product strong { display: block; color: var(--c-heading); }
        .product small { display: block; color: var(--muted); font-size: 14px; }
        .product em { margin-left: auto; color: var(--c-accent); font-style: normal; font-weight: 600; white-space: nowrap; }
        footer { margin-top: 48px; font-size: 13px; color: var(--muted); }
    </style>
</head>
<body>
    <main>
        <div class="orbit" aria-hidden="true"><span></span><span></span><b></b><i></i></div>
        <div class="eyebrow">OrderOrbit Space</div>
        <h1>Coming soon</h1>
        <p>We build technology for online commerce. Our new website is on its way.</p>
        <a class="product" href="{{ $growvia }}">
            <svg viewBox="0 0 100 100" aria-hidden="true"><g transform="translate(-1 1)" fill="currentColor"><rect x="22" y="72" width="56" height="8" rx="4"/><rect x="46.5" y="44.5" width="7" height="30"/><path d="M50 50 C 50 34, 38 24, 22 24 C 22 40, 34 50, 50 50 Z"/><path d="M50 44 C 50 28, 62 18, 80 18 C 80 34, 66 44, 50 44 Z" opacity=".8"/></g></svg>
            <span><strong>Growvia</strong><small>Bundles, upsells, gifts and checkout offers for Shopify stores.</small></span>
            <em>Visit →</em>
        </a>
        <footer>© {{ date('Y') }} OrderOrbit Space</footer>
    </main>
</body>
</html>
