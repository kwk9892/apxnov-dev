# Product Inventory Management API

A REST API for managing products, categories, and suppliers, built with Laravel 11.

## Stack

- Laravel 11.x, PHP 8.3+
- MySQL 8 (application database). The test suite runs against an in-memory SQLite database instead — see "Running tests" — so no MySQL server is needed just to run tests.
- Laravel Sanctum for token authentication
- L5-Swagger (OpenAPI) for API documentation

## Features

- `Product` belongsTo `Category`, `Product` belongsToMany `Supplier` (pivot: `product_supplier`)
- Full CRUD on products, categories, and suppliers
- Product listing supports filtering by `category_id`, `min_price`/`max_price`, `stock_level` (`out_of_stock` / `low_stock` / `in_stock`), and pagination via `per_page`
- Token authentication via Sanctum (`/api/register`, `/api/login`, `/api/logout`)
- Form Request validation (`StoreProductRequest`, `UpdateProductRequest`)
- API Resources for consistent response formatting (`ProductResource`, `CategoryResource`, `SupplierResource`)
- Eloquent scopes on `Product`: `category()`, `priceBetween()`, `stockLevel()`
- Accessor (`stock_status`, derived from `stock`) and mutator (`sku`, normalized to uppercase) on `Product`
- Soft deletes on `Product`
- Rate limiting on the `api` middleware group (60 req/min per user or IP)
- Response caching on the category list, invalidated on write
- OpenAPI/Swagger docs, served at `/api/documentation`
- Docker setup (PHP-FPM + Nginx + MySQL)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create the database (adjust host/credentials in .env to match your MySQL instance)
mysql -u root -e "CREATE DATABASE product_inventory_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
php artisan serve
```

`.env` ships with `DB_CONNECTION=mysql`, `DB_DATABASE=product_inventory_api`, `DB_USERNAME=root`, and no password, matching a default local MySQL install (e.g. Laragon). Update those four values if your setup differs.

## Running tests

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in `phpunit.xml`), so they don't need a MySQL server or touch your application database.

## API documentation

```bash
php artisan l5-swagger:generate
php artisan serve
```

Then visit `http://127.0.0.1:8000/api/documentation` for the interactive Swagger UI, or fetch the raw spec from `storage/api-docs/api-docs.json`. OpenAPI attributes are defined directly on the controllers (`app/Http/Controllers/Controller.php` for the base `Info`/`SecurityScheme`, and per-endpoint attributes on every `Api\*Controller`).

## Authentication flow

```bash
# Register
curl -X POST http://127.0.0.1:8000/api/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Jane","email":"jane@example.com","password":"password123","password_confirmation":"password123"}'

# Use the returned token as a Bearer token on subsequent requests
curl http://127.0.0.1:8000/api/products \
  -H "Authorization: Bearer <token>" -H "Accept: application/json"
```

## Filtering products

```
GET /api/products?category_id=1&min_price=10&max_price=500&stock_level=in_stock&per_page=20
```

## Docker

```bash
docker compose up -d --build
```

This runs PHP-FPM (`app`), MySQL (`db`), and Nginx (`webserver`, exposed on `http://localhost:8080`). Set `APP_KEY`, `DB_PASSWORD`, and `DB_ROOT_PASSWORD` in your environment or a `.env` file before running `docker compose up` (generate `APP_KEY` locally first with `php artisan key:generate`). The Docker setup was authored following standard Laravel + Nginx + PHP-FPM + MySQL patterns but has not been run in this environment (no Docker daemon available here) — verify the build locally before relying on it in CI/production.

## Known environment note

Running on PHP 8.5, Laravel 11's `config/database.php` references the `PDO::MYSQL_ATTR_SSL_CA` constant, which PHP 8.5 marks deprecated (superseded by `Pdo\Mysql::ATTR_SSL_CA`). It's cosmetic — the connection still works — but it will print a deprecation notice under `display_errors=On`. `composer audit` also flags a small number of `laravel/framework` advisories that are unpatched as of the newest available 11.x release (11.55.1) as of this writing; the project pins `^11.31` per the Laravel 11.x requirement in the brief. Upgrading to Laravel 12.x would resolve both, but was out of scope given the stated version requirement.

## Performance notes

- **Indexes on filtered columns**: `products` is indexed on `(category_id, price)`, plus standalone indexes on `stock` and `price`. The composite index alone can't serve a `stock_level` filter (different column) or a price-only filter with no `category_id` (price isn't the leftmost column in the composite) — both would be full table scans without the standalone indexes.
- **Avoided a redundant query in `ProductController::store()`**: creating a product with no `supplier_ids` still called `$product->load(['category', 'suppliers'])`, which queries the (empty) supplier pivot table needlessly — a newly created product can't have suppliers yet unless they were just synced. The relation is set directly to an empty collection when no suppliers were provided, dropping that path from 3 queries to 2.
- **`paginate()` on `/products` runs a `COUNT(*)` on every request** in addition to the page query. This is the standard pagination trade-off (needed for total-page/total-count metadata) and is left as-is; for very large catalogs a cursor-based pagination endpoint would avoid the count cost, but wasn't implemented since the brief calls for standard pagination.

## Design notes

- **SKU normalization**: SKUs are uppercased both in the Form Request (`prepareForValidation()`) and in the `Product::sku` mutator, so the uniqueness check and the stored value always agree — validating `"abc-1"` against a uniqueness rule before normalizing would otherwise let case-variant duplicates slip through.
- **Soft deletes**: `Product` uses `SoftDeletes`; deleted products are excluded from all default queries (including `show`), and the `deleted_at` timestamp is exposed on `ProductResource` for completeness.
- **Filtering**: implemented as query scopes (`scopeCategory`, `scopePriceBetween`, `scopeStockLevel`) on the model rather than inline in the controller, so they're reusable and independently testable.
