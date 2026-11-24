# Guul 12 — backend

Laravel 12 API (`/api/v1`) and Nova 5 admin (`backend/nova/`, switched on once Nova is installed — see `../docs/nova-setup.md`).

## API v1 (all JSON)

| Method | Path | Auth | What |
|---|---|---|---|
| GET | `/api/v1/config` | optional | Public settings, feature switches, Premium flag, `show_ads`. Never prices. |
| POST | `/api/v1/auth/device` | – | Anonymous account for an install (`install_id` UUID). Returns a token + student ID. |
| POST | `/api/v1/auth/google` | token | Optional Google sign-in (links, or merges this phone into the existing account). |
| POST | `/api/v1/auth/logout` | token | Revokes the token. |
| GET/PATCH | `/api/v1/me` | token | Own profile: grade, stream, retaker, region, city, gender, target score, reminder time, nickname, avatar. |
| GET | `/api/v1/catalog` | token | Subjects, units, chapter headers + sync cursor. |
| GET | `/api/v1/chapters/{id}` | token | Everything to study one chapter offline. |
| GET | `/api/v1/sync/changes?since=N` | token | Chapters changed since cursor N (paged). |
| GET | `/api/v1/sync/pack?subject_id=&grade=` | token, Premium | Whole subject + grade offline pack. |
| POST | `/api/v1/attempts` | token | Upload a finished practice set; the server scores it (idempotent by `client_uuid`). |
| GET/PUT/DELETE | `/api/v1/bookmarks[/{question}]` | token | Bookmarks. |

## Commands

```bash
composer check            # Pint + Larastan (level 8) + tests
php artisan db:seed       # exam subjects (safe in production)
php artisan db:seed --class=DemoDataSeeder   # local fake data, refuses production
```

## Environment

| Key | Meaning |
|---|---|
| `GOOGLE_CLIENT_IDS` | Comma-separated OAuth client IDs whose Google ID tokens are accepted |
