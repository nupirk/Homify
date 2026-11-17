# Deploying Homify API on a VPS (Ubuntu 24.04)

End-to-end walkthrough for a fresh Ubuntu 24.04 server with nginx, PHP-FPM,
PostgreSQL, a queue worker and the scheduler. Substitute your own domain and
paths; the examples use `api.example.com` and `/var/www/homify-api`.

## 0. What you need

- A VPS (2 vCPU / 2 GB RAM is enough to start) with root or sudo access.
- A DNS `A` record: `api.example.com` → VPS IPv4 address.
- This repository cloned somewhere private (it contains payment config code,
  not secrets).
- Live merchant credentials for Khalti / eSewa once you are ready to take
  payments.

## 1. Base packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx postgresql postgresql-contrib git unzip curl \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Node is only needed if you rebuild the marketing site assets
# (resources/css, resources/js); the API itself does not need it.
```

## 2. PostgreSQL

```bash
sudo -u postgres psql <<'SQL'
CREATE USER homify WITH PASSWORD 'change-this-strong-password';
CREATE DATABASE homify OWNER homify;
ALTER DATABASE homify SET timezone TO 'Asia/Kathmandu';
SQL
```

The schema uses PostgreSQL `ENUM` types and `jsonb`, so MySQL/SQLite are not
supported. PostgreSQL 14+ is fine.

## 3. Code, environment, first migration

```bash
sudo mkdir -p /var/www/homify-api
sudo chown -R "$USER":www-data /var/www/homify-api
git clone <YOUR_REPO_URL> /var/www/homify-api
cd /var/www/homify-api

composer install --no-dev --prefer-dist --optimize-autoloader

cp .env.example .env
php artisan key:generate
```

Now edit `.env`:

| Key | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://api.example.com` — gateway callbacks are built from this |
| `APP_PUBLIC_URL` | `https://example.com` (marketing site / OG base) |
| `TRUSTED_PROXIES` | `127.0.0.1` (nginx is local; never `*` in production) |
| `DB_*` | the `homify` database above |
| `EXPOSE_DEV_OTP` | `false` (must stay false) |
| `ADMIN_*` | real admin email / phone; leave `ADMIN_PASSWORD` empty to generate |
| `MAIL_*` | SMTP credentials — `log` silently drops password resets and OTPs |
| `SMS_GATEWAY` | `sparrow` for Nepal, `twilio` for international |
| `NOTIFICATIONS_QUEUE_PUSH` | `true` once the queue worker is running |
| `KHALTI_*` / `ESEWA_*` | live keys; set `*_ENABLED=true` only after the keys are in |

Then create the schema and seed:

```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force
```

The seeder prints the admin login. If it generated a password, copy it now —
it is shown once. Log in at `https://api.example.com/admin`.

Verify the admin's id is what the platform ledger expects:

```bash
php artisan tinker --execute="echo App\Models\User::where('user_type','admin')->value('user_id');"
```

On a fresh database this is `1`, matching `PLATFORM_USER_ID=1`. If it is not,
update `PLATFORM_USER_ID` in `.env` and run `php artisan config:cache`.

## 4. Permissions, caches, storage link

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

sudo -u www-data php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Re-run the four cache commands after any `.env` change (`config:cache` alone
is enough for env changes, but the others are cheap).

## 5. nginx + PHP-FPM

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/homify-api
sudo sed -i 's/api.example.com/YOUR_DOMAIN/g' /etc/nginx/sites-available/homify-api
sudo ln -s /etc/nginx/sites-available/homify-api /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

`deploy/nginx.conf` already denies dotfiles (`.env`, `.git`), sets
`client_max_body_size 20M` for KYC uploads and points FPM at
`/run/php/php8.3-fpm.sock`. Adjust the socket if you installed another PHP
version.

TLS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.example.com
```

Check the app answers before moving on:

```bash
curl -i https://api.example.com/up          # 200
curl -i https://api.example.com/api/v1/test # JSON from the API
```

## 6. Queue worker

Payments, notifications and pushes are queued (`QUEUE_CONNECTION=database`),
including OTP mail. Without a worker those jobs pile up silently.

```bash
sudo cp deploy/homify-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now homify-queue
sudo systemctl status homify-queue --no-pager
sudo journalctl -u homify-queue -f     # watch it process jobs
```

After changing code, restart it: `sudo systemctl restart homify-queue`.

## 7. Scheduler (cron)

Auto-assignment, payment expiry, cash-confirmation reminders and the nightly
`db:backup` are all scheduled inside the app.

```bash
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/homify-api && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

`db:backup` shells out to `pg_dump`; install `postgresql-client` (already
pulled in by `postgresql`) or set `PG_DUMP_BINARY` to its absolute path.
Backups land on the server — copy them off the box (S3, another host) so a
lost VPS does not take the data with it.

## 8. Gateway webhooks

Point the merchant dashboards at these URLs (all public, rate-limited, and
signature/amount-checked in the app):

| Gateway | URL |
| --- | --- |
| Khalti payments | `https://api.example.com/api/v1/webhooks/khalti/payment` |
| Khalti payouts | `https://api.example.com/api/v1/webhooks/khalti/payout` |
| eSewa payments | `https://api.example.com/api/v1/webhooks/esewa/payment` |
| eSewa success / failure | `https://api.example.com/api/v1/webhooks/esewa/success`, `/failure` |
| Membership eSewa | `https://api.example.com/api/v1/webhooks/membership/esewa/success`, `/failure` |
| Generic callback | `https://api.example.com/api/v1/payment/callback/{gateway}` |

`APP_URL` must be the public HTTPS origin or the eSewa redirect back to the
app will be built with the wrong host.

## 9. Updating a deployment

```bash
sudo -u www-data bash deploy/deploy.sh
```

It pulls, installs production dependencies, migrates, relinks storage, rebuilds
caches, restarts the queue worker and hits `/up`. First install only:

```bash
SEED=1 sudo -u www-data bash deploy/deploy.sh
```

## 10. Go-live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `EXPOSE_DEV_OTP=false`
- [ ] `APP_KEY` generated (a copied dev key must never reach production)
- [ ] `php artisan migrate:status` clean, seeder admin verified
- [ ] `/up` and `/api/v1/test` reachable over HTTPS
- [ ] Admin panel login works at `/admin`, password stored in a password manager
- [ ] Queue worker `active`, scheduler cron installed, one job seen in the log
- [ ] Mail test: password reset email arrives
- [ ] SMS test: OTP arrives (Sparrow/Twilio credit available)
- [ ] Gateway sandbox payment completes end-to-end, then switch to live keys
- [ ] `db:backup` ran once and the dump was copied off the server
- [ ] Firewall: only 22, 80, 443 open (`sudo ufw allow OpenSSH`, then nginx full)
- [ ] `.env` is not in git (`git check-ignore .env`)
