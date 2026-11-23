# App polish 1 report: easy warm-up, welcome slides, lively Home

**ONE PR:** #7 (`app-polish-top` → `nova-upgrade-top`). Merge #1 → … → #6 → #7 with "Create a merge commit".
**Your choices:** all three recommended options (DECISIONS #39).

## 1. What changed

| # | Part | Before | Now |
|---|---|---|---|
| 1 | Warm-up | 5 exam-level questions per grade (derivatives, free fall, oxidation…); some needed typing; Check + Next for each | **5 very easy questions, the same for everyone**: 7 + 5, water → ice, opposite of "big", capital of Ethiopia, 2-4-6-8-? Three big answers, **one tap**, a friendly message at once, then Next |
| 1b | Warm-up result | A trophy icon and a line | A trophy badge, **one star per right answer** and a kind line for any score |
| 2 | Welcome | One plain screen | **3 swipe slides** with original drawings in the brand colours: short lessons, real-exam practice, a little every day. Skip, dots, "Let's start" |
| 2b | Order | Welcome → grade → warm-up → profile | **Slides → warm-up → result → grade → profile.** Only setup questions are numbered ("Step 1 of 5") |
| 3 | Home | Plain list of white cards | **Coloured top card** (Good morning + nickname + avatar, today's-goal ring, streak, exam countdown), big **Continue** card, **colourful tiles**, **subject shortcuts**, **tip of the day** (10 tips, a new one each day), gentle one-time animations |

**Kept from before:**
- no streak text before the first answer;
- the daily-challenge comparison ("better than 64%");
- the offline, maintenance and Premium banners;
- no prices, buttons or links to payment (the Play-rules test checks every text);
- branding colours and light/dark mode on every new part;
- texts in the translation file (none hard-coded).

## 2. Screenshots (golden pictures, checked by eye)

Files are in `app/test/golden/goldens/` on the PR branch.

| Screen | Light | Dark |
|---|---|---|
| Welcome slide 1 | walkthrough_1_phone_light.png | walkthrough_1_phone_dark.png |
| Welcome slide 3 | walkthrough_3_phone_light.png | walkthrough_3_phone_dark.png |
| Warm-up, right answer | warmup_right_phone_light.png | warmup_right_phone_dark.png |
| Warm-up, wrong answer | warmup_wrong_phone_light.png | warmup_wrong_phone_dark.png |
| Warm-up result | warmup_result_phone_light.png | warmup_result_phone_dark.png |
| Home (phone) | home_phone_light.png | home_phone_dark.png |
| Home (tablet) | home_tablet_light.png | home_tablet_dark.png |
| Home, Nile branding | home_nile_phone_light.png | home_nile_phone_dark.png |

## 3. Numbers

| Check | Result |
|---|---|
| App tests | **378 passed**, including 70 goldens (18 new or changed, all other screens pixel-identical) |
| flutter analyze / dart format | 0 issues / clean |
| Warm-up answers | Solved a second time in the test, by answer text, not letter |
| gitleaks | Clean |
| CI (GitHub Actions) | **Green** at eaf31fd: App (analyze, 378 tests, goldens, release APK + app bundle, 8-permission check) and Secrets scan |

## 4. What I could NOT test

- On a real phone: I only tested in the test runner and the pictures. Please try a fresh install. The new welcome slides only show for a new student, so delete the app data or reinstall.
- The animations on a slow phone. They run once and are off when the phone asks for less motion.
- Somali and Amharic texts: the app UI is English for now.

## 5. Status

📍 App polish 1 · Step 4 of 4: done → next: your test + merges (#1 → #7)
📊 This job: ▓▓▓▓▓▓▓▓▓▓ 100% done · 0% left
📊 Whole project: ▓▓▓▓▓▓▓▓▓▓ 100% of the build done · launch waits on your tasks
⚠️ None. Ready for the other things you want changed.

**Your open tasks:**
- Nova on the server (licence);
- Google sign-in keys;
- AdMob IDs;
- Firebase (later);
- Telegram bot;
- WhatsApp number and email;
- upload key;
- textbook PDF links;
- videos;
- confirm prices and the icon;
- referral reward (QUESTIONS #40).

**The repo is PUBLIC; make it private when the project is finished.**
