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
| `premium_price_term_birr`, `premium_price_year_birr` | prices on the landing page `/premium` |
| `contact_whatsapp`, `contact_email` | how to pay on `/premium`; contact on the legal pages |
| `play_store_url` | download button on the landing page (only real `https://play.google.com/` links) |
| `exam_date` | countdown and study plan in the app |
| `ai_monthly_cap_usd` | stops new AI explanations for the month at this amount |
