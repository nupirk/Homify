# Homify API — production backend

Laravel 12 REST API and admin panel for the Homify home-services platform.
Runs on SQLite (default — one file, nothing to install) or PostgreSQL 14+.
The migrations, seeders and test suite work on both.

This folder is the deployable cut of the backend: application code, migrations,
production seeders and deployment files only. No `.env`, no `vendor/`, no
uploaded media, no dev scratch scripts.

## Stack

| Piece | Version / choice |
| --- | --- |
| PHP | 8.2+ (8.3 recommended) |
| Laravel | 12 |
| Database | SQLite, or PostgreSQL 14+ |
| Auth | Sanctum bearer tokens (API), session auth (admin panel) |
| Queue / cache / sessions | database driver (no Redis needed) |
| Payments | Khalti, eSewa (+ optional Stripe) |

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite (the .env.example default): create the database file once
mkdir -p database && touch database/database.sqlite

php artisan migrate --seed
php artisan serve          # API on http://localhost:8000
```

For PostgreSQL, uncomment the `DB_*` block in `.env`, create the database, and
run the same `migrate --seed`.

Health check: `GET /up`. API base: `/api/v1`. Admin panel: `/admin`.

## Tests

The suite uses `RefreshDatabase`, so point it at a disposable database —
everything in it is wiped. It passes on SQLite and on PostgreSQL.

```bash
cp .env.testing.example .env.testing   # set DB_* to a throwaway database
php artisan test
```

## Seeding

`php artisan db:seed` runs `ProductionSeeder`, which creates only what a live
environment needs — no demo customers, partners or orders. Every seeder is
idempotent and safe to re-run.

| Seeder | What it creates |
| --- | --- |
| `AdminSeeder` | The admin account from `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PHONE` / `ADMIN_PASSWORD`. Empty password → generates a strong one and prints it once. An existing admin keeps its password. |
| `CoreCatalogSeeder` | 5 categories, 10 services and one active "standard" NPR price each. |
| `CommissionRuleSeeder` | Default 80% partner / 20% platform commission per active service. |

Re-running `ProductionSeeder` restores the default catalog copy and prices —
edit catalog data in the admin panel once the platform is live.

The admin is `user_id 1` on a fresh database, which is what `PLATFORM_USER_ID`
(default `1`) needs for the platform ledger account. If you seed onto a
database that already has users, set `PLATFORM_USER_ID` to the admin's id.

## Structure

| Path | Contents |
| --- | --- |
| `app/Http/Controllers/Api/V1` | Customer, partner and payment API |
| `app/Http/Controllers/SuperAdmin` | Admin panel + ledger, payouts |
| `app/Services` | LedgerService, PaymentService, PricingService, NotificationService, AuditService |
| `app/Console/Commands` | `bookings:auto-assign`, `payments:expire`, `db:backup`, ... |
| `database/migrations` | Full schema, including the PAS payment model |
| `deploy/` | nginx, systemd queue worker, deploy script |

## Scheduled work

`routes/console.php` schedules auto-assignment, payment expiry, cash
confirmation reminders and nightly `db:backup`. On the server run
`php artisan schedule:run` every minute from cron — see `DEPLOY.md`.

## Production

See [DEPLOY.md](DEPLOY.md) for the full Ubuntu VPS walkthrough (nginx, PHP-FPM,
PostgreSQL, queue worker, cron, TLS, gateway webhooks).
