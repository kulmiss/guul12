# Nova upgrade report

**Repo:** kulmiss/guul12 · **ONE PR:** #6 (`nova-upgrade-top` → `phase-5-top`)
**Merge order:** #1 → #2 → #3 → #4 → #5 → #6, each with "Create a merge commit".
**Date:** 23 Nov 2026 · **Nova:** 5.7.6, tested in a separate copy with the licence. The licence is **not** in git.

## 1. In short

Nova was a plain admin with basic lists. Now it has:
- 3 dashboards, one per role;
- clear tables, filters that are remembered, and 15 lenses;
- safe actions: confirm, a CONFIRM word, an old → new log, and CSV export by role;
- a branding page that changes Nova, the landing page and the app;
- bell notifications, a useful search box, a Before → After activity log, and help text on empty lists.

Every role only sees its own work. This is tested for every list, action, filter, card and search.

## 2. What existed and what was added

| Area | Before (Phase 5) | Now |
|---|---|---|
| Menu | Flat list | Grouped (Dashboards, Students & Premium, Content, Community, Settings, System), icons, badges with open counts; each role sees only its sections |
| Dashboards | 1 dashboard with a few basic cards | 3 dashboards (Overview, Payments, Content), 37 cards, cached; Find student by ID card |
| Tables | Raw columns | Readable times (Addis Ababa), coloured badges, counts, % right, Br amounts, grouped student page, no N+1 (tested) |
| Question view | Text only | Preview with maths (KaTeX, self-hosted) and pictures, as students see it |
| New pages | — | Payment requests (queue), Nickname reports, Mock exams, Battles, Referrals |
| Filters | Few | Filters on 11 lists; the last filter on each list is remembered per staff member |
| Lenses | None | 15 on 6 lists, for example Premium ends in 7 days, Most reported, Hardest, Ready to publish, Missing explanation or diagram, Incomplete content, Open for 48 hours |
| Actions | Give / revoke Premium | + Extend, End now, Undo last (CONFIRM), Publish / Beta / Draft / Fixed + re-score, Difficulty, Move chapter, Duplicate, Bulk import (Draft only), Export for teacher, Export students (CSV, no personal data), Open reported item, Video access, Rebuild offline pack, Publish / Unpublish / Check / Preview mock, Refresh caches, Test Telegram, Test email, Backup now |
| Big changes | — | More than 100 questions → background job, with a bell message when done |
| Branding | Fixed Sunrise look | Branding page: logo, colours with WCAG AA check, 4 palettes, 4 fonts, roundness, live preview, 10-version history; applied to Nova, the landing page and the app |
| Notifications | — | Bell: new question report, new nickname report, payment waiting, backup failed, background job done |
| Search box (top) | All lists, noisy | 8 useful lists with subtitles (e.g. "Grade 11 · Social · Zebra1"), by role |
| Activity log | Raw JSON | "Before → After" line in the list, a table on the detail page, the raw data below; read-only |
| Empty lists | "No … matched" | Plus one line saying what belongs there and how it gets filled |
| Dark mode | Nova default | All new parts (preview, diff, help lines, branding page) checked in dark mode |

## 3. Problems found and fixed on the way

| # | Problem | Fix | Proof |
|---|---|---|---|
| 1 | A role could open or save a list outside its job by typing the address (e.g. a content checker editing a student). This existed since Phase 3 | Our abilities apply to view, save, delete and actions | NovaAccessMatrixTest |
| 2 | Filter and card lists were served to every role | Refused outside the job | Mutation-tested |
| 3 | Nova's built-in action record (`/nova/resources/action-events`) was readable by every role | Super admins only (DECISIONS #38) | ActionEventsNovaTest; all 4 guards mutation-tested |
| 4 | A 5-letter search ("zebra") was read as a student ID only, so 5-letter nicknames could not be found | Searches the ID **and** the nicknames | GlobalSearchNovaTest |
| 5 | N+1 queries on the Questions and Students lists | Eager loading | NovaTablesTest (3 vs 23 rows) |
| 6 | "Extra days" Premium would have crashed the grants list | Badge added | Test first |
| 7 | An unpublished mock was switched back on by the next content import | Own staff flag `unpublished_at` | Test |
| 8 | MySQL 8 reorders JSON keys, so 3 tests failed only in CI | `assertSameJson` helper | CI green |

## 4. Screenshots

Real Chrome, local copy with **demo data only** (fake names). Files are in `docs/screenshots/nova/` on the PR branch.

| View | Desktop light | Desktop dark | Phone |
|---|---|---|---|
| Overview dashboard | dashboard-desktop-light.png | dashboard-desktop-dark.png | dashboard-phone-light.png, dashboard-phone-dark.png |
| Search box ("zebra") | search-desktop-light.png | search-desktop-dark.png | — |
| Bell | bell-desktop-light.png | bell-desktop-dark.png | — |
| Students list | — | — | students-phone-light.png, students-phone-dark.png |
| Activity log | activity-log-desktop-light.png | — | activity-log-phone-dark.png |
| Before → After | activity-diff-desktop-light.png | activity-diff-desktop-dark.png | — |
| Empty list help | empty-referrals-desktop-light.png | empty-referrals-desktop-dark.png | — |
| Filters | filters-open.png | — | — |
| Question preview (maths) | question-preview-light.png | question-preview-dark.png | — |
| Branding page | branding-sunrise.png, branding-nile.png | — | — |
| Landing page | landing-desktop.png, landing-nile.png | — | landing-phone.png |

What the browser check proved (0 console errors on every page):
- The search box finds the student "Zebra1" with the subtitle "Grade 9 · Social · Zebra1".
- The bell shows the 3 payment messages, the question report and the nickname report.
- The log list shows "Difficulty: 2 → 4", and the detail page shows the Before/After table.
- The empty Referrals list shows its help line.
- Dark mode turns on (`dark` class) and every new part is readable.
- Earlier checks:
  - maths preview: 4 of 4 formulas drawn;
  - remembered filter: 10 → 2 → 2 → reset to 10;
  - branding: saving Nile changed Nova's main colour to `29, 78, 137` and the landing colour to `#1D4E89`.

## 5. Numbers

| Check | Result |
|---|---|
| Backend tests | **321 passed** (SQLite and MariaDB) |
| Nova tests (licensed copy) | **86 passed** (SQLite and MariaDB) |
| App tests | **365 passed**, **60 goldens** (incl. 2 brandings: Sunrise + Nile, light + dark) |
| Larastan / Pint / flutter analyze | 0 errors, 0 warnings (Larastan with and without Nova) |
| gitleaks | Clean on every push (run after each commit, before the push) |
| CI (GitHub Actions) | Backend, App, Content, Landing, Store pack, Secrets scan: green up to step 9; see the PR for the last run |
| Dashboard load time | Opening a card: **2.4 ms** on average, **89 ms** for all 37 cards (cached). Cache refresh: **4.7 s** with 20,000 students and 400,000 answers (every 10 min, in the background). Whole Overview page in real Chrome on the local test server: **2.4–2.5 s** |
| Lighthouse (landing) | 99–100 on all 10 pages, with Sunrise and with Nile + logo |

## 6. What I could NOT test

- Real Telegram and real email sending. The code paths are tested with fakes; the "Send test" buttons are ready for the server.
- A queue worker on the real server. The scheduler line (`queue:work --stop-when-empty`) is tested locally only.
- MySQL 8 locally. CI runs MySQL 8; I used MariaDB here.
- Nova on your real server with your licence. I tested only in my licensed copy.
- Two "first" reports on the same question in the very same moment can skip one bell message (QUESTIONS #64). The reports are always saved and counted.
- Syncing your PC folders. The computer was offline from step 8a, so please pull from GitHub.
- Screen readers in Nova.

## 7. How to run it on the server

```bash
cd /path/to/guul12/backend
git pull                      # after merging PR #1 … #6
composer install --no-dev --optimize-autoloader
php artisan migrate --force   # new tables: payment_requests, nickname_reports, branding_versions, mocks.unpublished_at …
php artisan storage:link      # once: branding logos are served from /storage/branding
php artisan optimize:clear    # clear old caches
php artisan optimize          # cache config, routes, views again
php artisan guul:stats        # fill the dashboard cache now (it refreshes every 10 minutes)
```

The cron line must exist (it runs the dashboards refresh, backups and background jobs):

```
* * * * * cd /path/to/guul12/backend && php artisan schedule:run >> /dev/null 2>&1
```

Nova itself:
- install it with your licence, then say "Nova is installed";
- the steps are in `docs/nova-setup.md`;
- `auth.json` and `.env` stay on the server and never go into git.

## 8. Status

📍 Nova upgrade · Step 10 of 10: done → next: your test + merges (#1 → #6)
📊 This job: ▓▓▓▓▓▓▓▓▓▓ 100% done · 0% left
📊 Whole project: ▓▓▓▓▓▓▓▓▓▓ 100% of the build done · launch waits on your tasks
⚠️ Problems / questions: none open. Referral reward (QUESTIONS #40) still needs your answer.

**My open tasks for you:**
- Nova setup on the server (licence);
- Google sign-in keys;
- AdMob IDs;
- Firebase (later, for push);
- Telegram bot token + group;
- WhatsApp number and email for payments;
- upload key;
- textbook PDF links;
- videos;
- confirm prices and the icon.

**The repo is PUBLIC; make it private when the project is finished.**
