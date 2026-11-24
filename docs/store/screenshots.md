# Store screenshots plan

Google Play needs **2–8 phone screenshots** (JPEG or 24-bit PNG, no transparency, each side 320–3840 px,
the long side at most twice the short side). Use **1080 × 2340** or **1080 × 1920** portrait.
Seven-inch and ten-inch tablet screenshots are optional; add them later from a tablet emulator.

Take them from the **release build with real content** (not the test pictures in `app/test/golden`,
which use fake chapters). Use a test account with Premium so no ads show, and a nickname such as "Ayan".

## The 8 screens, in this order

| # | Screen | How to get there | Caption (optional, top band) |
|---|---|---|---|
| 1 | Home with today's plan | Open the app after a few days of practice | Your study plan for today |
| 2 | Chapter lesson | Study → Physics → a chapter with a formula | Short lessons for every chapter |
| 3 | Question with explanation | Chapter → Quick 10 → answer → Check | Clear explanations for every answer |
| 4 | Mock exam in progress | Exams → Physics → mock exam → Start | Real exam mode with a timer |
| 5 | Exam result by chapter | Finish the mock exam | See your weak chapters |
| 6 | Progress and mastery | Progress tab | Watch your progress grow |
| 7 | Weekly leaderboard | Progress → See the weekly leaderboard | Friendly weekly leaderboard |
| 8 | Dark theme | Me → Theme → Dark, then any chapter | Works offline, day or night |

Captions are English for the main listing. Somali and Amharic captions are drafts for native review
(same rule as [listing-so.md](listing-so.md) and [listing-am.md](listing-am.md)).

## Must not appear in any screenshot

- A price, "Buy", a payment method, a phone number, WhatsApp or any hint of how to pay (Play payments policy).
  "🔒 Premium" on a locked item is fine.
- A real student's name, student ID, email or city. Use the review test account only.
- Ads (use the Premium test account).
- Text that says "best", "#1" or "official".
- Copied textbook pages. Our own lesson text is fine.

## How to capture (no phone needed)

```bash
# 1. Start a Pixel-size emulator (Android Studio → Device Manager), then:
cd app
flutter run --release --dart-define=API_BASE_URL=https://guul12.kulmiss.com
# 2. On each screen:
adb exec-out screencap -p > ../docs/store/screens/01-home.png
```

Keep the raw captures out of git if they show anything personal. Final, checked screenshots can live in
`docs/store/screens/` (the repo is public, so check each one first).

## Other pictures (already made)

| Picture | File | Made by |
|---|---|---|
| App icon 512 × 512 | `app/tool/icon/play-store-512.png` | `python3 app/tool/icon/make_icon.py` (run in `app/`) |
| Feature graphic 1024 × 500 | `docs/store/feature-graphic.png` | `python3 docs/store/tool/make_feature_graphic.py` |
