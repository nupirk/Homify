# Homify — what's left, and how to do it

The code is done: brand, landing page, production seeders, deploy files and CI
are in the repo. What remains is credentials, server setup and the mobile
release. This page is the short version; `DEPLOY.md` has the full command-by-command
walkthrough.

Status of each area at a glance:

| Area | State | What is missing |
| --- | --- | --- |
| Backend (API + admin) | Ready | A server and real credentials |
| Database schema + seed | Ready, verified | Nothing |
| CI (audit + tests) | Ready | Nothing — runs on every push to `main` |
| Payments | Sandbox code ready | Live Khalti / eSewa merchant keys |
| Email (OTP, password reset) | Code ready | SMTP credentials |
| SMS (OTP login) | Code ready | Sparrow or Twilio credit |
| Mobile app | Renamed, typechecks | API URL, EAS build, store listing |

---

## 1. Credentials to collect first

Everything below goes into the server's `.env` (never into git). Each one blocks
a feature until it is set.

| Variable | Needed for | Where to get it |
| --- | --- | --- |
| `APP_KEY` | everything | `php artisan key:generate` on the server |
| `APP_URL` | payment redirects | your API domain, `https://api.example.com` |
| `DB_*` | everything | the PostgreSQL database you create |
| `ADMIN_EMAIL`, `ADMIN_PHONE` | admin login | your own inbox / phone |
| `ADMIN_PASSWORD` | admin login | leave empty → seeder prints a strong one once |
| `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | OTP email, password reset | Resend SMTP (username `resend`, password = API key) or any SMTP provider |
| `SMS_GATEWAY`, `SPARROW_SMS_TOKEN` (or `TWILIO_*`) | phone OTP at signup/login | Sparrow SMS dashboard (Nepal) / Twilio console |
| `KHALTI_PUBLIC_KEY`, `KHALTI_SECRET_KEY`, `KHALTI_WEBHOOK_KEY` | Khalti payments | Khalti merchant dashboard (start in sandbox) |
| `ESEWA_MERCHANT_CODE`, `ESEWA_PRODUCT_CODE`, `ESEWA_SECRET_KEY` | eSewa payments | eSewa merchant dashboard |
| `PLATFORM_USER_ID` | ledger owner | `1` on a fresh database (the seeded admin) |

## 2. Stand the server up

Follow `DEPLOY.md` sections 1–7. The condensed version:

```bash
# packages, database, code
sudo apt install -y nginx postgresql php8.3-fpm php8.3-{pgsql,mbstring,xml,curl,zip,bcmath,gd,intl}
git clone https://github.com/nupirk/Homify.git /var/www/homify-api
cd /var/www/homify-api
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env && php artisan key:generate

# fill in .env (see the table above), then
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force

# serve it
sudo cp deploy/nginx.conf /etc/nginx/sites-available/homify-api
sudo ln -s /etc/nginx/sites-available/homify-api /etc/nginx/sites-enabled/
sudo certbot --nginx -d api.example.com
sudo cp deploy/homify-queue.service /etc/systemd/system/
sudo systemctl enable --now homify-queue
```

Then install the scheduler cron (`DEPLOY.md` §7) and confirm
`https://api.example.com/up` returns 200 plus a JSON body from
`/api/v1/test`.

**Do not skip the queue worker or the cron entry.** OTP mail, push
notifications and payment reconciliation are queue jobs; without a worker they
pile up silently. Without cron, auto-assignment and payment expiry never run.

## 3. First login

`ProductionSeeder` creates three things: your admin account, 5 categories with
10 services and NPR prices, and an 80/20 commission rule per service.

```bash
php artisan db:seed --class=ProductionSeeder --force
# prints: Admin ready: you@example.com (user_id 1)  [+ a generated password]
```

Log in at `https://api.example.com/admin`. Re-running the seeder is safe: it
never resets an existing admin's password.

## 4. Payments

1. Start with the sandbox keys and `KHALTI_ENABLED=true` / `ESEWA_ENABLED=true`
   to prove the flow end to end.
2. Register the webhook URLs in each dashboard (all listed in `DEPLOY.md` §8) —
   `…/api/v1/webhooks/khalti/payment`, `…/api/v1/webhooks/esewa/payment` and the
   payout/membership variants.
3. Switch to live merchant keys, re-run `php artisan config:cache`, then make one
   real small-value booking and confirm the ledger entry in the admin panel.

Leave a gateway disabled until its live keys are in place: an enabled gateway
with placeholder keys fails every initiation with a 500.

## 5. Email and SMS (the login path depends on it)

Signup and login send a one-time code. If mail or SMS is not configured, users
cannot get in.

- `MAIL_MAILER=log` silently drops codes — use SMTP.
- `SMS_GATEWAY=log` does the same for phone OTP — use `sparrow` (Nepal) or `twilio`.
- Keep `EXPOSE_DEV_OTP=false` on any host real users can reach.

Test both before announcing anything: request a password reset (email) and sign
up with a real number (SMS).

## 6. Mobile app release

The Expo app lives outside this repository. Before shipping:

1. Point `EXPO_PUBLIC_API_URL` at the production API origin (release builds
   refuse http and placeholder hosts — see `app.config.js`).
2. Decide on the bundle id. It is currently `com.nupirk.urban`, which is tied to
   the existing Play Store listing; changing it means a new listing under
   `com.nupirk.homify`.
3. Replace the icon/splash artwork with Homify branding (still the old assets).
4. Build with EAS (`eas build --profile production`), then submit.
5. Update the Play badge link on the landing page once the listing is live — it
   still points at the `com.nupirk.urban` package.

## 7. CI

`.github/workflows/ci.yml` runs on every push to `main`:

1. installs PHP 8.3 + the extensions the app uses,
2. `composer install` from the lock file,
3. **`composer audit`** — any advisory fails the build,
4. creates `.env.testing` from `.env.testing.example` and points it at a
   `postgres:16` service container,
5. `php artisan migrate --force` (fails fast if Postgres is unreachable),
6. **`php artisan test`** — any failing test fails the build.

If it goes red, the failing step names the problem: an advisory points at a
package and its fixed version, a test failure prints the assertion. Reproduce
locally with `composer audit` / `php artisan test` after
`cp .env.testing.example .env.testing` and pointing `DB_*` at a scratch database.

Dependabot is not configured; when `composer audit` starts failing, bump the
flagged packages with `composer update <package> --with-all-dependencies`.

## 8. Before you call it live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `EXPOSE_DEV_OTP=false`
- [ ] Fresh `APP_KEY` on the server (never reuse a dev key)
- [ ] `php artisan migrate:status` clean; admin login works; password stored in a manager
- [ ] `/up` and `/api/v1/test` reachable over HTTPS
- [ ] Queue worker `active`, cron installed, one job shows up in `journalctl -u homify-queue`
- [ ] Password-reset email arrives; SMS OTP arrives
- [ ] One sandbox payment completes end to end, then live keys with `config:cache`
- [ ] `db:backup` ran once and the dump was copied off the server
- [ ] Firewall: only 22, 80, 443 open
- [ ] `git check-ignore .env` — the env file is not in the repo

## 9. Known carry-overs (intentional)

| Item | Why it still says "urban" |
| --- | --- |
| `urban_plus` plan code | Stored on live memberships and referenced by config; only the display name changed ("Homify Plus"). |
| `com.nupirk.urban` bundle id | Tied to the existing Play Store listing and EAS project. |
| i18n keys (`newToUrban`, `urbanPlus`) | Translation keys are identifiers used across the app; the displayed text is already Homify. |
