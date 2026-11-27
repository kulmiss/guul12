# Running the server

## Every minute: the scheduler

Add one cron line on the server (it runs backups, the watchdog and the quality gate):

```cron
* * * * * cd /path/to/guul12/backend && php artisan schedule:run >> /dev/null 2>&1
```

| Job | When (Addis Ababa time) | What |
|---|---|---|
| `guul:backup` | every night 02:30 | Gzipped database backup in `storage/app/backups`, newest 14 kept |
| `guul:watchdog` | every 10 minutes | Checks the server; alerts staff only when something changes |
| `quality:promote` | every night 03:15 | Beta questions with enough clean answers become Published |
| `queue:work --stop-when-empty` | every minute | Background work started from Nova (big question changes, "Run backup now"); stops when there is nothing to do |

## Branding logos

Logos uploaded in Nova → Settings → Branding are stored in `storage/app/public/branding` and served from `/storage/branding/…`. Run once on the server: `php artisan storage:link`.

## Backups

- Files: `backend/storage/app/backups/guul12-<UTC time>.sql.gz` (MySQL) — readable only by the server user.
- They contain student data: **never** put them in git, email or chat. Copy them off the server over SFTP to an encrypted drive (a weekly copy is a good habit).
- Manual backup: `php artisan guul:backup`.
- Restore (to an empty database):
  ```bash
  gunzip < storage/app/backups/guul12-20261124-233000.sql.gz | mysql -u USER -p DATABASE
  ```
- `.env`: `GUUL_BACKUPS_REQUIRED=true` on the server (the watchdog then complains when the newest backup is older than 26 hours). `GUUL_BACKUPS_KEEP=14` to change how many are kept. If `mysqldump` is not on the PATH, set `GUUL_MYSQLDUMP=/full/path/to/mysqldump`.

## Watchdog alerts (Telegram, optional)

1. Create a bot with @BotFather and copy its token.
2. Add the bot to a private staff group and get the group's chat ID.
3. In the server `.env`: `TELEGRAM_BOT_TOKEN=...` and `TELEGRAM_OPS_CHAT_ID=...` (never in git).

It reports: database not answering, storage not writable, no recent backup, less than 10% disk free, more than 80% of the month's AI budget used — and "All good again" when fixed. Without a bot, alerts go to `storage/logs/laravel.log`.

## Uptime monitor

Point a free uptime monitor (for example UptimeRobot) at `https://guul12.kulmiss.com/health`. It answers `{"status":"ok"}` when the database works and `503` when not. No details are public. Laravel's own `/up` only shows that PHP runs.

## Settings to fill in Nova (App Settings → add a row per key)

| Setting | Used for |
|---|---|
| `premium_price_term_birr`, `premium_price_year_birr` | prices in the Premium part of the home page (`/#premium`) |
| `contact_whatsapp`, `contact_email` | how to pay on the home page; contact on the legal pages; WhatsApp is also the app's Premium-page button |
| `contact_telegram` | Telegram name (or t.me link) for the app's Premium-page button and the home page's Premium part. Switch `premium_contact` off to hide both app buttons at once |
| `play_store_url` | download button on the landing page (only real `https://play.google.com/` links) |
| `exam_date` | countdown and study plan in the app; "N days left" and the exam year on the website (hidden when empty or past) |
| `ai_monthly_cap_usd` | stops new AI explanations for the month at this amount |

## The website (landing pages)

- **Numbers and subjects** (9 subjects, 82 chapters…) are counted live from the content students can open, and refresh every 10 minutes. Nothing to type.
- **Reviews**: Nova → Settings → **Website reviews**. Add only real words from a real student who agreed. A review shows when both "Student gave permission" and "Show on the website" are ticked (at most 6, smallest "Order" first). With no review, the section is hidden.
- **App pictures** come from `app/test/golden/landing_screens_test.dart` (real lessons, drawn at 2×). After changing the app's look, run in `app/`:
  `flutter test test/golden/landing_screens_test.dart --update-goldens && tool/landing_images.sh`
  and commit the new `backend/public/img/landing/*.webp`.
- Pages linked from the app (`/app`, `/privacy`, `/terms`, `/delete-account`) never show a price, a contact for paying, or a link to `/` (a test checks every word). The old `/premium` address redirects to `/#premium`.

## The domain and HTTPS (guul12.kulmiss.com)

Found in the bug hunt (27 Nov 2026): `guul12.kulmiss.com` points to the cPanel server `91.204.209.17`,
but the subdomain is **not set up in cPanel**. Over HTTP the server shows its "default web page"
(`/cgi-sys/defaultwebpage.cgi`). Over HTTPS another site on the same server (abcobs.com, a WordPress
site on PHP 7.0) answers with **its own certificate**, so phones and browsers report a name mismatch,
and the app treats it as "offline". Steps (in cPanel, as the account that should own the site):

1. **Domains → Create a new domain**: `guul12.kulmiss.com`. Untick "Share document root". Document
   root: the Laravel `public` folder, e.g. `/home/<user>/guul12/backend/public`.
2. **MultiPHP Manager**: set `guul12.kulmiss.com` to **PHP 8.3** (8.2 is the lowest that works).
   **Select PHP Extensions** (or MultiPHP INI): `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `gd`,
   `zip`, `fileinfo`, `openssl`, `curl`.
3. **SSL/TLS Status**: tick `guul12.kulmiss.com` and press **Run AutoSSL** (Let's Encrypt or
   Sectigo). DNS already points to the server, so it can be checked over port 80. Wait until the
   row shows a green lock.
4. DNS: keep `guul12` as an **A record to 91.204.209.17**. If it is later put behind Cloudflare
   (orange cloud), set Cloudflare SSL to **Full (strict)** and `TRUSTED_PROXIES=*` in `.env`.
5. Check from any computer: `curl -I https://guul12.kulmiss.com/health` must answer `200` with no
   certificate error, and `https://guul12.kulmiss.com/` must show the Guul 12 page.

Server `.env` lines for production (never in git):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://guul12.kulmiss.com
SESSION_SECURE_COOKIE=true
# Only behind a proxy or Cloudflare (comma-separated addresses, or *):
TRUSTED_PROXIES=
```

## Staff sign-in

Every staff member can turn on **two-step sign-in** in Nova: user menu (top right) → **Security** →
Two-factor authentication → scan the QR code with an authenticator app (Google Authenticator,
Microsoft Authenticator, Aegis) → type the code → keep the recovery codes somewhere safe. Recommended
for every super admin. Sign-in is limited to 5 tries a minute per email and 20 per address.

