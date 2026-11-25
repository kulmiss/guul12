# Landing redesign report: "Bold" website

**PRs:** #8 (`landing-top` → `develop`) ✅ merged. #9 (app screens row) ✅ merged. #10 (phone view, same branch `landing-gallery-top`) ✅ merged.
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
| 10 | Scrollbars + polish (your request) | App pictures and the phone menu scrolled sideways with a scrollbar | **No horizontal scrollbar anywhere.** App pictures: see row 12. Phones: swipe with a hidden bar, faded edges and "Swipe to see more →"; the menu wraps. Plus centred titles with an accent line, cards and buttons that lift, round step numbers, gently floating phones, a dotted hero pattern, shorter cards on phones. A CI check fails if any page ever scrolls sideways (320–1920 px) |
| 11 | Grades 8–12 (your decision #43) | Website said Grade 12 only | Titles, hero, FAQ, terms say **Grades 8–12**; new section **"One app from Grade 8 to Grade 12"** with 5 grade cards; "Grades" in the menu; the countdown names the Grade 12 national exam. Somali + Amharic drafts too |
| 12 | App screens (your pick + your video) | 5 + 4 grid on a dark band, black phone frames, dark shadows | **One simple row** on every screen, on the light background like the rest of the site. Each screen is a **white rounded card with the same soft shadow** as the other cards. The row **lines up with the page text** and fades softly at both edges. Swipe, trackpad or shift + wheel; scrollbar hidden; "Swipe or scroll sideways to see more →" chip |
| 13 | Phone view (your video) | Menu in 2 messy rows, buttons of different widths, every list one long column (page ~12,600 px on a phone) | **One-row header** with a menu button (dropdown), full-width buttons, countdown box, numbers 3 + 2, **grades and reviews swipe**, features 2 columns, steps and "How to get Premium" as a **timeline**, subjects 2 tiles, plans side by side, footer 2 columns, see-through bottom bar. Page now ~9,750 px |

**To convert visitors:** exam countdown, trust ticks (free to start, works offline, every answer checked twice), real numbers, real screens, a Free vs Premium table followed by the plans and contact buttons, FAQ answering the usual worries, a final call, and the bottom bar on phones.

**Kept safe:**
- Prices only on the home page's Premium part (`/#premium`).
- `/app` and the legal pages (linked from the app) never show a price, a payment contact, or a link to `/`. A test checks every word.
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

Sent in chat: `g_computer.png`, `g_phone.png` (Grades 8–12), `screens_computer.png`, `screens_phone.png` (app screens row), `phone_view.png` (phone view: top, menu, grades, features, screens, Premium), `final_amharic_phone.png`.
The app pictures are in `app/test/golden/goldens/landing/` and `backend/public/img/landing/`.

## 4. Numbers

| Check | Result |
|---|---|
| Backend tests | **335 passed** on SQLite and MariaDB; Pint clean; Larastan 0 |
| Nova tests | **89 passed**; Larastan with Nova 0 |
| App tests | **408 passed** (9 new landing pictures; all other pictures unchanged); analyze 0; format clean |
| Layout check | 9 pages × 7 widths (320–1920 px): no sideways scrolling, no horizontal scrollbar, the app screens row scrolls and can be reached by keyboard, and the header stays one row (it failed on the old layouts, passes now) |
| Lighthouse in CI (full content) | Every page: performance 99–100, accessibility 100, best practices 100, SEO 100 (Somali/Amharic drafts are noindex on purpose) |
| gitleaks | Clean |
| CI | Green: app, backend (PHP 8.2, 8.3, MySQL 8), Lighthouse, secrets |

## 5. What I could NOT test

- The pages on a real phone and on your real server.
- Somali and Amharic texts: drafts for a native speaker.
- The countdown number in the screenshots uses a sample exam date I set locally (29 June 2027). Set the real `exam_date` in Nova.

## 6. Status

📍 Landing redesign · Step 10 of 10: all merged into `develop` → next: your next request
📊 This job: ▓▓▓▓▓▓▓▓▓▓ 100% done · 0% left
📊 Whole project: ▓▓▓▓▓▓▓▓▓▓ 100% of the build done · launch waits on your tasks
⚠️ None.

**Later tasks (we do them before launch):** lessons, questions and mocks for Grades 11, 10, 9 and 8 (Grade 8 needs research first); Grade 8 in the app and backend; Play store texts for Grades 8–12.

**Your open tasks:**
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
