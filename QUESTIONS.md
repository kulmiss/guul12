# QUESTIONS (small technical choices I made myself)

| # | Choice | Reason |
|---|---|---|
| 1 | Monorepo: `backend/`, `app/`, `landing/`, `docs/` | One PR per phase covers all parts |
| 2 | Students and staff share the `users` table with a `role` column (student, super_admin, payments_helper, content_checker) | One auth system; Nova uses the same table |
| 3 | Student ID format `ST-XXXXX`, alphabet without 0/O/1/I/L (31 chars → 28.6 million IDs) | Easy to read aloud and type on WhatsApp |
| 4 | Install ID (random UUID made by the app) is stored only as a SHA-256 hash | Less sensitive data in the database |
| 5 | Sanctum personal access tokens, one per install; re-registering the same install revokes the old token | Simple, works offline-first |
| 6 | Google ID tokens verified offline against Google's JWKS (cached 1 h), audiences from `GOOGLE_CLIENT_IDS` | No extra Google SDK on the server |
| 7 | Google sign-in on a new phone moves that phone's attempts, bookmarks, devices **and Premium grants** into the existing account, then deletes the anonymous account | Nothing paid or studied is lost |
| 8 | Fill-in and numeric are two technical types (`fill_in`, `numeric`) for the one "fill-in / numeric" type in the brief → 9 enum values for the 8 brief types | Numeric needs tolerance and units |
| 9 | Answer keys travel with the content (needed for instant offline feedback); the server re-scores every uploaded answer and never trusts the phone's result | Offline-first + anti-cheat |
| 10 | Answers faster than 800 ms are flagged `too_fast`; time per answer capped at 10 min (both editable settings) | Anti-cheat signals for the leaderboard (Phase 4) |
| 11 | Question `version` goes up when a student-visible question's stem, key, explanation, media, passage or options change | Re-scoring after a fix (Phase 2/3) knows which version was answered |
| 12 | Sync uses an append-only `content_changes` table as the cursor | Works on shared hosting, no extra services |
| 13 | Larastan level 8 (level max needs typed request DTOs everywhere — planned refactor, not needed for safety) | Strict but practical |
| 14 | PHP 8.2 minimum (CI tests 8.2 and 8.3) | Shared hosting compatibility |
| 15 | Nova code lives in `backend/nova/` (namespace `App\Nova`), loaded only when `laravel/nova` is installed | Install, CI and tests never need the licence |
| 16 | Premium plan lengths: term 120 days, year 365 days (editable settings) | Ethiopian school term ≈ 4 months |
| 17 | A new Premium grant starts when the current one ends (stacking) | Students never lose paid days |
| 18 | App uses the `material_ui` package (Flutter 3.50 moved Material widgets out of the SDK; `flutter/material` is deprecated) | No deprecation warnings |
| 19 | Region stored as a code (`somali`, `addis_ababa`, …): Ethiopia's 12 regions + 2 chartered cities (2023 list). Somali is first | Labels can be translated later; focus region is one tap away |
| 20 | Stream question only for Grades 11–12; "retaking the exam" question only for Grade 12 | Streams start in Grade 11; only Grade 12 sit the national exam |
| 21 | The 5-question warm-up in onboarding is bundled and stays on the phone (never uploaded) | Works offline on first start; no fake question IDs on the server |
| 22 | Free students download a chapter when they first open it (needs internet once); whole-subject packs stay Premium | Matches DECISIONS #11 (confirmed by owner) |
| 23 | Practice sets: Quick 10 = up to half "last answer wrong" + new questions; Mixed 20; chapter/unit up to 30; Quick revise 5; Timed = 60 s per question | Spaced repetition proper comes in Phase 3 |
| 24 | Practice shows right/wrong and the explanation straight after "Check" | Learning mode; exams (Phase 3) show results only at the end |
| 25 | Ordering questions never start in the right order; items move by drag **or** up/down buttons | Fair question; works with screen readers and small screens |
| 26 | Theme (phone / light / dark) is saved on the phone only | Not personal data, no need to sync |
| 27 | Android: minSdk 24 (Android 7.0), internet permission only, app-data backup off | Old phones supported; token never leaves the phone |
| 28 | Noto Sans is subset to Latin + Greek + common symbols (−1.1 MB). Ethiopic fonts come with the Amharic UI | Smaller download for students on mobile data |
| 29 | Golden (screenshot) tests use the real fonts and a pinned Flutter (3.50.1) in CI | Pixel comparisons stay stable |
| 30 | Reminder time is saved in the profile now; the daily reminder notification (WorkManager, inexact) is built in Phase 4 | Phase 4 = engagement |
| 31 | The router re-checks redirects only when "onboarded" or the minimum build changes | A test found that refreshing on every profile edit undid the "Save" screen close |
| 32 | Answer letters inside a chapter file may follow a pattern (a reviewer noticed A, B, C, D runs). They are kept: the app always shuffles question order in practice, and fixed mocks mix chapters and will be checked so their answer keys show no runs | Re-lettering 2,400 checked questions would void their second solves for no student-visible gain |
| 33 | Summary PDFs are free for everyone for now (asked the owner; one line in `ContentPayload` to change) | They spread on Telegram and carry only the price-free landing link |
| 34 | Fixed mocks for now: 3 per subject × 50 choice questions, 1.5 min per question (75 min), mocks 1–2 free, 3 Premium; Aptitude has 2 (its bank has 103 choice questions). Asked the owner; `make-mocks.py --size --mocks --free --minutes-per-question` remakes them | Real paper sizes and times are not published by EAES |
