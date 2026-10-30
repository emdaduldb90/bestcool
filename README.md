# Best Cool Electronics

Laravel 12 storefront built with Blade and Tailwind CSS. The imported catalog, public site content, settings and uploaded images are bundled with the project.

## Requirements

- PHP 8.2 or newer with the PDO MySQL extension
- MySQL 8 or MariaDB
- Node.js 20 or newer and npm
- Composer

## Local setup

1. Copy `.env.example` to `.env` and set `DB_PASSWORD` to the local MySQL password. The default database is `bestcoolelectronics` and the default username is `root`.
2. Create the database in MySQL, then run:

```powershell
composer install
npm install
npm run build
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000`. For frontend hot reload during development, run `npm run dev` in a second terminal instead of building assets.

On Windows, `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start-bestcool.ps1` starts the Laravel server and opens the site. Add `-NoBrowser` to keep the browser closed.

## Included storefront

- Responsive storefront, search, category filters and product pages
- Session cart, wishlist, customer accounts and Cash on Delivery checkout
- Imported category/product catalog, public settings, homepage sections and uploaded images
- Order and inventory updates for COD checkout
- Protected admin panel for products, categories, delivery zones, orders, homepage banners and public store settings

Sign in at `/account/login`; administrators are sent to `/admin`. On a fresh database, create an account through the storefront and grant it admin access with `php artisan admin:promote email@example.com`.

Online payment gateways are not configured in this Laravel storefront.
