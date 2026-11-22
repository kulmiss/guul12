# Guul 12

Exam-prep app for Ethiopian secondary students (Grades 9–12) preparing for the Grade 12 national exam
(ESSLCE), with a special focus on the Somali Region. Publisher: **Kulmiss**.

| Folder | What | Status |
|---|---|---|
| `backend/` | Laravel 12 API + Nova 5 admin (Nova switched off until installed) | Phase 1 |
| `app/` | Flutter Android app (iOS-ready code) | Phase 1 |
| `landing/` | Landing page on `guul12.kulmiss.com` | Phase 5 |
| `docs/` | Setup guides (`nova-setup.md`, …) | |

Progress: [PROGRESS.md](PROGRESS.md) · Decisions: [DECISIONS.md](DECISIONS.md) · Small choices: [QUESTIONS.md](QUESTIONS.md)

## Backend quick start

```bash
cd backend
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan db:seed --class=DemoDataSeeder   # local fake data only, refuses production
php artisan serve
composer check                                # Pint + Larastan + tests
```

## Rules

- The repo is **public** for now: never commit `.env`, the Nova licence, AdMob IDs, Firebase files,
  upload keys, tokens, real student data, receipts or backups. gitleaks runs on every PR.
- Payments happen outside the app (Google Play rules): the app shows no price, no buy button and no payment link.
