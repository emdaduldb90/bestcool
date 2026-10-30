# Best Cool Electronics Admin Panel

This guide covers the Laravel 12 admin panel under `/admin`. It extends the existing Blade, Tailwind, Vite, session cart, and COD checkout application.

## Feature audit

| Area | Status | Notes |
| --- | --- | --- |
| Dashboard and reporting | Existing + fixed | Real database totals, date filters, order status totals, product/category sales, stock alerts. Order value is not reported as collected revenue. |
| Products and FAQs | Existing + extended | Search/filter/export, safe bulk visibility, SEO, multiple uploads/URLs, image alt text/primary/order, typed FAQ editing, discount preview. Stock edits go through Inventory. |
| Brands and categories | Existing + extended | Brand management renames the existing `products.brand` string; category CRUD includes active/order/SEO and blocks deletion while products use it. |
| Orders and delivery | Existing + extended | Filters, selected CSV, printable invoice/packing slip, status history, guarded transitions, stock-safe cancellation, active delivery zones and protected zone deletion. |
| Inventory | Added | Audited signed adjustments, non-negative stock validation, movement history, low-stock filters, CSV export. |
| Home builder and CMS | Existing + extended | Typed editors for existing block types, order/up/down controls, visibility, slug/SEO controls, and frontend rendering. Home services/about and multiple banners are editable. |
| Website settings | Existing + extended | General, branding uploads, contact/WhatsApp, header/footer links, social links, SEO, home services/about; validated structured inputs invalidate the storefront cache. |
| Customers and staff | Added | Registered customers only, customer order history/contact/status controls, predefined least-privilege roles, staff lifecycle, guarded legacy super-admin access, profile and password change. |
| Contact inbox | Added | Rate-limited/honeypot contact form, inbox statuses, internal notes, safe delete. Mail is reported as configured only when the mail transport is not `log`; no message is claimed as sent. |
| Activity/system tools | Added | Admin audit trail, application/PHP versions, storage write check, mail transport status, authorized cache clearing. No environment or SQL-console exposure. |
| SEO | Extended | Product/category/page metadata, canonical and Open Graph output where data exists, streamed sitemap, robots response. |
| Promotions and tracking | Not implemented | Coupons, GA4/Meta/TikTok event tracking, and verified payment collection are intentionally out of scope for this release. Coupons need end-to-end COD checkout validation and persisted discounts before an admin screen is useful. |

## Roles and permissions

All privileged routes use server-side permission middleware; hiding a menu item is not the authorization check. The legacy `users.is_admin` flag remains supported and is mapped to Super Admin by the access seeder.

| Role | Main access |
| --- | --- |
| Super Admin | All listed admin permissions. Existing legacy admin accounts retain full access. |
| Store Manager | Dashboard, products, categories, inventory, orders, customers, content, settings, reports, activity, system. |
| Orders Staff | Dashboard, orders, customer/message view and order/message processing. |
| Inventory Staff | Dashboard and inventory. |
| Content Editor | Dashboard and pages, banners, home content. |
| Support | Dashboard, order view, customer and message view. |

Staff accounts are created in **Staff & roles** with a 12-character minimum password and a predefined role. Super Admin accounts cannot be changed from that form; the command checks that another active Super Admin remains before revoking one.

## Database and data preservation

Migration `2026_10_30_000002_expand_admin_and_storefront_management` is additive and has a `down()` method. It adds metadata/visibility/order fields and creates role, permission, stock ledger, order history, activity, and inquiry tables. It does not replace seeded products, categories, pages, orders, settings, or image URLs.

For an existing database, back up the database first, then from the Laravel project directory run:

```powershell
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\AdminAccessSeeder" --force
```

Run only the access seeder on an existing store. Do not run `migrate:fresh`, `db:wipe`, or the full `DatabaseSeeder` against real store data.

To grant access to an existing account (the account must already exist):

```powershell
php artisan admin:promote user@example.com
```

To revoke it safely, while retaining at least one active Super Admin:

```powershell
php artisan admin:revoke user@example.com
```

The commands prompt before changing access. `--force` skips that prompt and should be used only in an intentional deployment operation.

## Local run and checks

Install dependencies only when they are missing. Keep the current `.env` and database configuration; do not replace it with `.env.example` on an existing installation.

```powershell
composer install
npm install
php artisan migrate
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`. For live CSS/JS iteration, run `npm run dev` in a second terminal instead of the production build. Set the database connection in the local `.env`; this application supports MySQL and SQLite.

## Settings and image storage

The typed settings page supports:

- `site.name`, `site.tagline`, `site.logo`, `site.favicon`
- `nav.header`, `footer.columns`, `footer.text`, `social.links`
- `contact.phone`, `contact.email`, `contact.address`
- `whatsapp.number`, `whatsapp.message`
- `seo.defaultTitle`, `seo.defaultDescription`
- `home.services`, `home.about.heading`, `home.about.html`

Admin uploads use unique filenames in `public/uploads/products`, `public/uploads/categories`, and `public/uploads/branding`. Existing `/uploads/...` references continue to work. Ensure the web server can write to the relevant `public/uploads` subdirectories. Uploaded image validation checks actual image type and size; externally hosted images must use HTTPS on product/settings forms.

## Shared hosting / VPS deployment

1. Use PHP 8.2 or newer with the required Laravel extensions, Composer, and Node/npm at build time.
2. Point the web server document root to this project's `public` directory; do not expose the project root or `.env` over HTTP.
3. Configure the existing MySQL database and application key in the server environment. Back up production data before applying migrations.
4. Run `composer install --no-dev --optimize-autoloader`, `npm ci`, and `npm run build` during deployment. Deploy the compiled `public/build` assets.
5. Run `php artisan migrate --force`; run the `AdminAccessSeeder` command above once to attach permissions to existing admin accounts.
6. Grant write access to `storage`, `bootstrap/cache`, and the upload directories. Confirm `APP_DEBUG=false` and HTTPS/secure cookies in production configuration.
7. After replacing code, clear/rebuild Laravel caches as appropriate (`php artisan optimize:clear`; optionally `php artisan config:cache` and `php artisan route:cache`).

The app does not require Docker, Redis, a queue worker, WebSockets, or an external SaaS service for the implemented admin features. Contact messages are still stored if SMTP is not configured; this release does not send notification emails.
