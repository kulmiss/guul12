# PROGRESS

📍 Landing redesign · Step 9 of 9: app screens as one simple sideways row → next: owner review + merge (PR landing-top → develop)
📊 This job: ▓▓▓▓▓▓▓▓▓▓ 100% done · 0% left
📊 Whole project: ▓▓▓▓▓▓▓▓▓▓ 100% of the build done · launch waits on owner tasks
⚠️ Waiting for: owner review + merge of the landing PR; real student reviews (Nova → Website reviews); exam_date in Nova; Play account, upload key, AdMob IDs, Google sign-in IDs, Nova licence on the server

| Phase | Weight | Status |
|---|---|---|
| 0 Research + plan | 4% | ✅ done |
| 1 Backend core + app core | 24% | ✅ done — waiting for owner test (PR #1) |
| 2 Content + quality gate + Grade 12 | 22% | ✅ done — waiting for owner test (PR #2) |
| 3 Exams + adaptive + SRS | 16% | ✅ done — waiting for owner test (PR #3) |
| 4 Engagement + ads + AI + Premium | 20% | ✅ done — waiting for owner test (PR #4) |
| 5 Landing + ops + store + Nova | 14% | ✅ done — waiting for owner test (PR #5) |
| Nova upgrade (extra job) | — | ✅ done — waiting for owner test (PR #6) |
| App polish 1 (owner feedback) | — | ✅ merged (PR #7 → develop) |
| Landing redesign (owner feedback) | — | ✅ done — waiting for owner review (landing PR) |

## Landing redesign steps

| # | Step | Status |
|---|---|---|
| 1 | 3 full mockups (Bold, Minimal, Playful); owner picked Bold | ✅ |
| 2 | Real app pictures with real lessons (golden test, 2×, WebP) | ✅ |
| 3 | Live numbers + subject cards, exam countdown, real-only reviews (Nova), brand-safe colours | ✅ |
| 4 | Bold design on home, /app, /premium, legal pages; Somali + Amharic drafts | ✅ |
| 5 | Tests, Lighthouse on full content, PR | ✅ |
| 6 | Premium moved into the home page (`/premium` redirects), decision #42 | ✅ |
| 7 | No horizontal scrollbars + UI polish; CI layout check (QUESTIONS #72) | ✅ |
| 8 | Website for Grades 8–12 (decision #43) | ✅ |
| 9 | App screens: one simple sideways row everywhere, scrollbar hidden (QUESTIONS #73) | ✅ |

## Later tasks (owner: "we will do that later")

| # | Task | Status |
|---|---|---|
| L1 | Lessons, questions and mock exams for Grade 11 | ⏳ later |
| L2 | Lessons, questions and mock exams for Grade 10 | ⏳ later |
| L3 | Lessons, questions and mock exams for Grade 9 | ⏳ later |
| L4 | Grade 8: research the Grade 8 leaving exam and subjects, then lessons, questions and mocks | ⏳ later |
| L5 | App + backend accept Grade 8 (today 9–12) | ⏳ later (owner: not yet) |
| L6 | Play store texts and screenshots for Grades 8–12 | ⏳ later |

## App polish 1 steps (PR #7)

| # | Step | Status |
|---|---|---|
| 1 | Warm-up: 5 very easy tap-only questions, the same for all grades | ✅ |
| 2 | 3 welcome slides with original drawings, before the warm-up | ✅ |
| 3 | Lively Home dashboard | ✅ |
| 4 | Tests, goldens, PR | ✅ |
| 5 | Home extras: this week, weakest chapter, next badge | ✅ |
| 6 | Become Premium card on Home + Premium benefits page with WhatsApp/Telegram (decision #40, no prices) | ✅ |

## Nova upgrade steps (PR #6)

| # | Step | Status |
|---|---|---|
| 1 | Inventory, plan, owner decisions (#31–35) | ✅ |
| 2 | New tables + grouped menu with icons and badges | ✅ |
| 3 | Nickname reports (app + API + Nova) | ✅ |
| 4 | Payment requests queue (+ closed two role gaps) | ✅ |
| 5 | Dashboards (cached, per role) | ✅ cards 2.4 ms; refresh 4.7 s at 20,000 students |
| 6 | Clearer tables, question preview (maths + pictures), mock/battle/referral pages | ✅ no N+1 (tested) |
| 7 | Filters + lenses, last filter remembered | ✅ |
| 8 | Actions (confirm, CONFIRM word, old → new log, queued bulk, CSV by role) | ✅ |
| 9 | Branding page (Nova + landing + app) | ✅ Lighthouse 99–100 |
| 10 | Bell notifications, global search, Before → After log, empty states, dark mode, screenshots, report | ✅ 321 backend + 86 Nova + 365 app tests, 60 goldens |

## Phase 5 steps

| # | Step | Status |
|---|---|---|
| 1 | Landing page in Laravel (en + so/am drafts), price-free /app, privacy, terms, delete-account | ✅ Lighthouse 100/100/100/100 |
| 2 | Account deletion in the app and the API (Play requirement) | ✅ |
| 3 | Ops: daily backups, watchdog + /health, Telegram alerts | ✅ |
| 4 | App icon + store pack (listings, Data safety, app content, feature graphic) | ✅ CI "Store pack" green |
| 5 | Release build check + version docs | ✅ `check_apk.py` in CI; biometric permissions removed |
| 6 | Nova switch-on checks + dashboard | ✅ 5 new cards, 11 Nova tests |
| 7 | CI for landing (Lighthouse) | ✅ 10 pages, 100/100/100/100 |
| 8 | Phase 5 report + final summary | ✅ 235 backend + 348 app tests; CI green at 98b2ab6 |

## Phase 4 steps

| # | Step | Status |
|---|---|---|
| 1 | Streak, XP, badges (worked out on the phone) | ✅ |
| 2 | Daily reminder (WorkManager, inexact, no exact alarms) | ✅ |
| 3 | Nickname + built-in avatars, weekly leaderboard, daily challenge | ✅ |
| 4 | Battles (quick battle, friend code; no chat) | ✅ |
| 5 | Referral (reward is a setting; owner to confirm) | ✅ |
| 6 | Ads: small banner above the tabs + rewarded video; teen, non-personalised | ✅ |
| 7 | Explain more (Claude Haiku 5.5, Premium, off without a key) | ✅ |
| 8 | Premium flow (neutral end notes, staff guide) | ✅ |
| 9 | CI green, Phase 4 report | ✅ 216 backend + 345 app tests |

## Phase 3 steps

| # | Step | Status |
|---|---|---|
| 1 | Backend: mock list/download (Premium check), exam results by chapter, "better than X%" after 30 students, answer history | ✅ |
| 2 | Random mock from the bank: 1 per Ethiopian day free, no answer-key patterns | ✅ |
| 3 | App: Exams tab, Real Exam Mode (no pause, flags, question map), result + review, estimated score range | ✅ |
| 4 | Nova ON (tested with Nova 5.7.6, 4 bugs fixed) + MySQL 8 CI job | ✅ |
| 5 | Mistakes notebook + spaced repetition (1, 3, 7 days) | ✅ |
| 6 | Smart practice, chapter strength, weak topics, Progress tab | ✅ |
| 7 | Automatic study plan + last-30-days revision mode | ✅ |
| 8 | CI green, Phase 3 report | ✅ 179 backend + 264 app tests |

## Phase 2 steps

| # | Step | Status |
|---|---|---|
| 1 | Content pipeline + quality gate (YAML, `content:check`, review sheets, independent second solve, Beta, re-scoring) | ✅ |
| 2 | "Report a problem" in the app; report review in Nova (off) | ✅ |
| 3 | Content: Mathematics, Physics, Chemistry, Biology (44 chapters) | ✅ |
| 4 | Content: English, Aptitude, Geography, History, Economics (38 chapters) | ✅ 82 chapters, 2,460 questions, all Beta |
| 5 | Summary PDFs (82) + fixed mocks (26) | ✅ |
| 6 | CI green, Phase 2 report | ✅ |

## Phase 1 steps

| # | Step | Status |
|---|---|---|
| 1 | Repo, monorepo layout, CI (backend, gitleaks) | ✅ |
| 2 | Backend schema, models, roles, settings, feature switches, audit log | ✅ |
| 3 | Backend API v1 (device auth, Google sign-in, profile, config, catalog, chapter sync, changes, packs, attempts, bookmarks) + Nova code (off) | ✅ 129 tests |
| 4 | App skeleton: theme (Sunrise, light/dark), translations, routing | ✅ |
| 5 | App onboarding (grade → 5-question warm-up → profile) | ✅ |
| 6 | App study browser, 9 question types, LaTeX, practice modes | ✅ |
| 7 | App offline storage + sync, sign-in, student ID | ✅ 175 app tests |
| 8 | App CI, golden tests, release build, Phase 1 report | ✅ CI green (backend, app, secrets) |
