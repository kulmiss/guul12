# App polish 2 report: Home cards + Statistics page

PR: #12 (`app/04-home-stats` → `develop`). Details: QUESTIONS.md #76.

## What you asked

"Add what you would recommend. I also want a page for statistics with full data and charts."

## What is new on Home

| Card | Shows | When it shows |
|---|---|---|
| Review time | Questions due today + **Start review** button | When reviews are due (replaces the small Review tile) |
| Exam readiness | Gauge + likely score range + change this week | After the first answer; before 20 answers it says how many more |
| Your rank this week | Place, weekly XP, XP to move up one place | Online, with a nickname, leaderboard on |
| Card of the day | One flashcard, tap to turn, "More cards" | When a saved chapter has flashcards |
| Statistics tile | Opens the Statistics page | After the first answer |

## The Statistics page

Open it from Progress → "See your statistics", or from the Home tile.

| Part | Chart |
|---|---|
| 8 numbers | Answered, % right, days studied, best streak, exams, best exam, study time, time per question |
| Likely exam score | Gauge with range and change this week |
| Last 14 days | Right/wrong bars |
| Accuracy by week | Line, 8 weeks |
| Exam scores | Line, last 10 exams |
| Accuracy by subject | Bars |
| Chapters by strength | One split bar + legend |
| Activity | 12 weeks, one square a day |
| When you study | Morning / afternoon / evening bars |

- Tap a bar, point or square to see its numbers.
- Screen readers hear every value.
- Light and dark mode.
- No new package: the app draws the charts itself.

## Study time

- The phone now saves the time spent on each answer (database version 4).
- Old answers have no time, so study time counts from this version on.
- One answer counts at most 5 minutes, so a phone left open does not add hours.
- Nothing new is sent to the server.

## Proof

| Check | Result |
|---|---|
| flutter analyze | No issues |
| Format check | Clean |
| All tests (incl. goldens) | 447 passed |
| New tests | 23 domain, 4 database (incl. update from version 3), 8 widget; they fail without the new code |
| Release APK build | Built (3 APKs) |
| gitleaks | No leaks |

## Not tested

- On a real phone (there is no emulator here).
- The rank card against the real server. It was tested with the fake server, which uses the same JSON.

## Reminders

- **The repo is PUBLIC; make it private when the project is finished.**
- Your open tasks:
  - Nova setup on the server: WhatsApp, Telegram and email; `exam_date`; `play_store_url`.
  - Google sign-in keys, AdMob IDs, Firebase, Telegram bot.
  - Upload key, PDF links, videos.
  - Real reviews.
  - Confirm prices, the icon and the referral reward (QUESTIONS #40).
