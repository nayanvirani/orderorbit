# OrderOrbit

Shopify CRO, Checkout & Customer Experience Platform — *Convert more customers. Increase order value. Bring customers back.*

Laravel 13 app that serves both the public website and the Shopify embedded app, deployed on Railway with PostgreSQL.

## Structure

| Area | Where |
| --- | --- |
| Public website (Part A) | `routes/web.php` → `SiteController`, `resources/views/site` |
| Embedded app (Part B) | `/app/*` routes, `app/Http/Controllers/App`, `resources/views/app` (Polaris web components + App Bridge) |
| Shopify auth | `AuthenticateShopify` middleware: App Bridge session tokens + token exchange (Shopify managed install) |
| Webhooks | `POST /webhooks/shopify` (HMAC-verified, idempotent via `webhook_receipts`) |
| Billing | `App\Services\Shopify\Billing` — Starter / Growth / Scale via `appSubscriptionCreate` |
| Shopify app config | `shopify.app.toml` (managed with Shopify CLI) |

## Configuration

All secrets are Railway service variables — see `.env.example` for the names. Nothing secret is committed.

## Deploy

- Pushing to `main` deploys the `orderorbit` Railway service (Railpack build; pre-deploy `php artisan orderorbit:deploy` (migrations + template sync); health check `/up` — set on the service).
- App config (URLs, scopes, webhooks) and extensions: `shopify app deploy`.

## Storefront runtime

Source lives in `resources/storefront` (a small core plus one file per experience type). Build it into the theme app extension before deploying the extension:

```bash
cd scripts/storefront && npm install && npm run build   # writes extensions/orderorbit-theme/assets
shopify app deploy
```

The build fails if any file exceeds Shopify's 10 KB app-block JavaScript guideline.

## Tests

```bash
php artisan test
```

## Brand

Logo files live in `public/brand/` (served at `/brand/...`):

| File | Use |
|---|---|
| `orderorbit-logo.svg` / `.png` | Full logo (mark + wordmark), dark, for light backgrounds |
| `orderorbit-logo-white.svg` / `.png` | Full logo for dark backgrounds |
| `orderorbit-logo-color.svg` | Full logo with the mark in brand violet |
| `orderorbit-icon.svg` | App icon (rounded), used by the admin and as `/favicon.svg` |
| `orderorbit-icon-square.svg` | App icon without rounded corners |
| `orderorbit-icon-shopify-1200.png` | Shopify app icon (1200×1200, Shopify rounds the corners) |
| `orderorbit-icon-512.png`, `apple-touch-icon.png`, `favicon-32.png` | Smaller icon sizes |
| `orderorbit-mark.svg` / `-white.svg` / `-512.png` | The mark alone, no background |
| `orderorbit-feature-1600x900.png` | Shopify App Store listing feature image |
| `orderorbit-widgets-1080p.mp4` | 55-second animated tour of every widget (for YouTube / feature media) |

The website draws the mark from the `#i-orbit` symbol (`resources/views/site/partials/icons.blade.php`), so it takes the text colour. Brand violet: `#6d5dfc` → `#3b1fb8`. Wordmark: Instrument Serif, converted to outlines.
