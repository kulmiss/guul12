# Landing redesign report: "Bold" website

**ONE PR:** #8 (`landing-top` → `develop`). Merge with "Create a merge commit".
**Your choices:** Bold style (from 3 mockups), real app screens made by me, all pages, all 4 section groups + "anything that converts" (DECISIONS #41).

## 1. What changed

| # | Part | Before | Now |
|---|---|---|---|
| 1 | Look | Plain cream page, one teal box, 6 white cards | Dark brand-colour top part with a glow and a wave, tilted phone pictures with stickers, coloured icon cards, dark picture band, orange offline band, dark footer |
| 2 | Home sections | 3 | **12**: hero, numbers, 3 steps, 8 features, app pictures, subjects, offline band, reviews, Free vs Premium, FAQ, final call, footer |
| 3 | App pictures | None | **9 real screens with real Grade 12 physics lessons** (Home, lesson, explanation, exam, result, progress, plan, leaderboard, dark mode) |
| 4 | Numbers | None | **Live**: 9 subjects · 82 chapters · 2,460 questions · 722 flashcards · 26 mock exams. They update by themselves when content changes |
| 5 | Exam countdown | None | "217 days left until the national exam" from `exam_date` in Nova. Hidden when empty or past |
| 6 | Reviews | None | **Nova → Settings → Website reviews.** Hidden until you add a real one |
| 7 | Premium | A separate `/premium` page | **On the home page** (your request, decision #42): the Free vs Premium table, then plan cards with prices ("Best value"), 4 steps, **WhatsApp / Telegram / email buttons**. Old `/premium` links redirect there |
| 8 | Phones | — | A **"Get Guul 12 free"** bar stays at the bottom while scrolling |
| 9 | Somali / Amharic | Old texts | Every new text as a **draft** for native review |
| 10 | Scrollbars + polish (your request) | App pictures and the phone menu scrolled sideways with a scrollbar | **No horizontal scrollbar anywhere.** Computers/tablets: pictures in a neat grid. Phones: swipe with a hidden bar, faded edges and "Swipe to see more →"; the menu wraps. Plus centred titles with an accent line, cards and buttons that lift, round step numbers, gently floating phones, a dotted hero pattern, shorter cards on phones. A CI check fails if any page ever scrolls sideways (320–1920 px) |

**To convert visitors:** exam countdown, trust ticks (free to start, works offline, every answer checked twice), real numbers, real screens, a Free vs Premium table with a "See Premium plans" button, FAQ answering the usual worries, a final call, and the bottom bar on phones.

**Kept safe:**
- Prices only on the home page's Premium part (`/#premium`).
- `/app` and the legal pages (linked from the app) never show a price, a payment contact, or a link to `/` or `/premium`. A test checks every word.
- **No fake reviews.** You asked for placeholders. I did not publish them: invented reviews mislead students and break Google Play and consumer rules. The section stays hidden until you add a real review.

## 2. How to add a real review

| Field | What to type |
|---|---|
| First name | First name only (e.g. Hodan) |
| Grade, City | e.g. 12, Jigjiga |
| What the student said | Their own words, 10–300 characters |
| Stars | 1–5 |
| Student gave permission | Tick only if they agreed |
| Show on the website | Tick to show it (needs the permission tick) |

## 3. Screenshots

Sent in chat: `new_ui_computer.png`, `new_ui_phone.png` (latest look), `final_amharic_phone.png`.
The app pictures are in `app/test/golden/goldens/landing/` and `backend/public/img/landing/`.

## 4. Numbers

| Check | Result |
|---|---|
| Backend tests | **333 passed** on SQLite and MariaDB; Pint clean; Larastan 0 |
| Nova tests | **89 passed**; Larastan with Nova 0 |
| App tests | **408 passed** (9 new landing pictures; all other pictures unchanged); analyze 0; format clean |
| Layout check | 9 pages × 7 widths (320–1920 px): no sideways scrolling, no horizontal scrollbar (it failed on the old layout, passes now) |
| Lighthouse in CI (full content) | Every page: performance 99–100, accessibility 100, best practices 100, SEO 100 (Somali/Amharic drafts are noindex on purpose) |
| gitleaks | Clean |
| CI | Green: app, backend (PHP 8.2, 8.3, MySQL 8), Lighthouse, secrets |

## 5. What I could NOT test

- The pages on a real phone and on your real server.
- Somali and Amharic texts: drafts for a native speaker.
- The countdown number in the screenshots uses a sample exam date I set locally (29 June 2027). Set the real `exam_date` in Nova.

## 6. Status

📍 Landing redesign · Step 7 of 7: no horizontal scrollbars, nicer UI → next: your review + merge of PR #8
📊 This job: ▓▓▓▓▓▓▓▓▓▓ 100% done · 0% left
📊 Whole project: ▓▓▓▓▓▓▓▓▓▓ 100% of the build done · launch waits on your tasks
⚠️ None.

**Your open tasks:**
- Nova on the server (licence);
- Google sign-in keys;
- AdMob IDs;
- Firebase (later);
- Telegram bot;
- WhatsApp number, Telegram name and email (Nova settings);
- `exam_date` and `play_store_url` (Nova settings);
- real student reviews (Nova → Website reviews);
- upload key;
- textbook PDF links;
- videos;
- confirm prices and the icon;
- referral reward (QUESTIONS #40).

**The repo is PUBLIC; make it private when the project is finished.**
