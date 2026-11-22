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
