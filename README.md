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

- Pushing to `main` deploys on Railway (migrations run as the pre-deploy step, health check `/up`).
- App config (URLs, scopes, webhooks) and extensions: `shopify app deploy`.

## Tests

```bash
php artisan test
```
