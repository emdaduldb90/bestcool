# Tracking & AI Operations Guide

## What is installed

The panel is available at `/admin/tracking` from the existing admin login and uses the existing `tracking.view` and `tracking.manage` permissions. The storefront collector and consent endpoints are same-origin Laravel routes. Browser tags are not loaded and first-party events are not stored until the visitor makes a consent choice.

The pipeline stores one canonical event per `event_id`, records browser/server observations separately, applies consent-aware routing, and queues provider deliveries. Provider delivery errors do not fail checkout. Analytics uses consented events and labels cash-on-delivery order values as orders placed, not paid revenue.

## Database setup

The tracking migration is additive. After deploying the code to another environment, run:

```powershell
php artisan migrate --force
php artisan db:seed --class=TrackingSystemSeeder --force
```

The migration and seeder have already been run in this project. Do not run `migrate:fresh`, a database reset, or the full application seeder on production. The existing admin seeder is not required to seed tracking destinations.

## Configure destinations

Open **Admin → Tracking & AI → Configuration**. Secret fields are encrypted with Laravel's application key, hidden after save, and replaceable or revocable. Do not put provider secrets in chat, browser settings, HTML, or source control. Keep `APP_KEY` backed up; changing it makes encrypted credentials unreadable.

| Provider | Browser configuration | Server configuration | Notes |
| --- | --- | --- | --- |
| Meta | Pixel ID | Pixel ID, Conversions API access token, Graph API version | Add a Test Events code to send a synthetic test event. |
| TikTok | Pixel ID | Pixel ID, Events API access token | Add a Test Events code to send a synthetic test event. |
| GA4 | Measurement ID | Measurement ID and Measurement Protocol API secret | Server delivery also needs a consented `_ga` client ID; browser and server Purchase are not both enabled by default. |
| OpenAI | None | API key, model, language and token/report limits | Reports are generated on demand from consented aggregate data. |

Destinations start disabled. Enable browser and server modes independently, then save event routing. Pixel and measurement IDs are public identifiers; tokens and API secrets remain server-side. No credentials were configured or provider APIs called during implementation.

## Event semantics

The installed events are `PageView`, `ViewContent`, `AddToCart`, `InitiateCheckout`, `Purchase`, `LinkClick`, `PhoneClick`, `EmailClick`, and `Search`. Search, product, cart, checkout, and link capture are connected to the existing storefront. Cart and checkout events come from successful Laravel actions; a browser click cannot create a cart mutation or purchase.

The shop currently places COD orders with `PENDING` status and has no verified payment-capture event. `Purchase` therefore means **COD order placed** in this integration. It is not proof that payment was collected. Admin metrics and AI reports say so explicitly. Do not use these counts as paid revenue or ad-attributed conversions. Add a real payment-capture domain hook before changing the milestone for a future payment method.

Funnel percentages compare event counts and are not unique-customer conversion rates. Provider acceptance is transport status only; it does not prove reporting or attribution. GA4's Measurement Protocol debug endpoint validates payload shape but does not verify the API secret or guarantee event processing.

## Queue and scheduler

The application uses its configured Laravel queue connection; in the current deployment this is the database queue. Nothing calls Meta, TikTok, GA4, or OpenAI from a customer-facing page or checkout request. A background runner is needed for prompt delivery.

For a persistent worker where supported:

```bash
php artisan queue:work database --queue=tracking --sleep=3 --tries=3 --timeout=60
```

For shared hosting without a persistent worker, configure the host cron to run Laravel's scheduler every minute:

```cron
* * * * * cd /path/to/bestcool-laravel && php artisan schedule:run >/dev/null 2>&1
```

The scheduler runs a bounded delivery batch each minute and retention cleanup daily. This can delay events until the next cron run. For a manual bounded run:

```bash
php artisan tracking:deliver-pending --limit=50
```

The scheduled command can process the local tracking outbox directly; a persistent Laravel worker can also process the queued delivery jobs. A host administrator must confirm cron/worker health because the application cannot verify an external process from the web panel.

## Test events

Run the server-side local pipeline self-test from the project directory:

```powershell
php artisan tracking:self-test
```

It simulates all nine canonical events with synthetic consent inside a rollback-only database transaction. It checks collection, server/browser event-ID correlation for cart and checkout, observations and aggregate updates. It fakes HTTP and the queue, verifies the temporary data was rolled back, creates no real order, and sends no provider event. It reports provider enablement and credential presence without revealing credentials. This checks Laravel's local event pipeline; it does not prove a real browser tag fired or a provider received an event.

Use the provider's own test/debug interface. Set the provider test code first, then click **Test destination** in Configuration. The Meta and TikTok actions send one synthetic `PageView` test event; remove the test code before relying on live-mode events. Their provider test panels must show receipt. Receipt is still not evidence of attributed conversions.

The GA4 test sends a minimal payload to Google's Measurement Protocol debug endpoint and reports payload validation only. Verify actual events separately in GA4 DebugView/Realtime. The OpenAI test sends a small Responses API request and may incur API usage. All tests are explicit admin actions, not automated production conversion calls.

Official references:

- [Meta Conversions API](https://developers.facebook.com/docs/marketing-api/conversions-api/)
- [TikTok Events API](https://business-api.tiktok.com/gateway/docs/index?doc_id=1771100779668482&language=ENGLISH)
- [GA4 Measurement Protocol](https://developers.google.com/analytics/devguides/collection/protocol/ga4/reference)
- [OpenAI Responses API](https://developers.openai.com/api/reference/resources/responses/methods/create)
- [OpenAI Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs)

## Consent, privacy and retention

Analytics and advertising consent are separate. The visitor token is random, secure, `HttpOnly`, `SameSite=Lax`, and stored as an HMAC hash in MySQL. The consent choice expires after six months. Provider tags load only for the matching accepted category. Admin sessions and sensitive paths are excluded. Revoking a category cancels matching pending server deliveries and prevents manual retry; events already delivered to a provider are not deleted automatically. Link query strings/fragments, phone numbers, email addresses, IP addresses and raw customer records are not sent to the AI service. GA4 server delivery uses a consented browser client ID only when available.

Default retention is 180 days for canonical events and deliveries, 730 days for daily/link aggregates, and 365 days for AI reports and audit logs. Override these with `TRACKING_RETENTION_DAYS`, `TRACKING_AGGREGATE_RETENTION_DAYS`, and `TRACKING_AUDIT_RETENTION_DAYS`, then clear cached configuration. The visitor can reopen **Privacy choices** in the storefront and revoke optional consent; revocation stops future collection, while existing rows age out by retention.

Safe link CSV is available under **Link analytics**. Customer and admin reports are permission-gated. CSV values are escaped against spreadsheet formula execution.

## Verification commands

```powershell
php artisan migrate:status
php artisan test --compact
npm.cmd run build
php artisan route:list --path=tracking
```

Tests use the test database and mocked application behavior; they do not send production provider events. The production JavaScript build is generated with Vite.

## Recovery

Back up MySQL before any rollback. Rolling back the tracking migration drops all tracking tables and their tracking history. If that is intended, first take and verify the backup, then use `php artisan migrate:rollback --step=1` only when this tracking migration is the latest migration in that environment. To restore the feature after a rollback, deploy the migration and run the setup commands above. Never roll back the application's store/admin migrations as part of tracking recovery.
