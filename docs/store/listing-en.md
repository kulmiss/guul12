# Google Play listing – English (main language)

Copy each block into Play Console → Grow → Store presence → Main store listing.
The limits are Google's. `tool/check_listing.py` checks them (see the end of this file).

## App name (max 30)

```text
Guul 12: Grade 12 Exam Prep
```

## Short description (max 80)

```text
Lessons, practice questions and mock exams for the Ethiopian Grade 12 exam.
```

## Full description (max 4000)

```text
Guul 12 helps Grade 12 students in Ethiopia get ready for the national exam (ESSLCE). It is made with special care for students in the Somali Region, and it works for every student in the country.

What you get
• All exam subjects: English, Mathematics, Scholastic Aptitude, Physics, Chemistry, Biology, Geography, History and Economics, for the natural and social science streams.
• Short lessons for every chapter: summaries, key formulas, solved examples and flashcards.
• More than 2,000 original practice questions, each with a clear explanation.
• Mock exams in real exam mode: a timer, a question map and your result by chapter.
• A daily study plan: tell the app how many minutes you have, and it mixes new chapters, weak topics and revision.
• Progress you can see: mastery per chapter, a streak, XP and badges.
• A gentle daily reminder at the time you choose.
• Daily challenge, a friendly leaderboard and question battles with a friend's code.
• Works offline: chapters you open stay on your phone.
• Links to the official textbooks.

Private by design
• No sign-up needed. Google sign-in is optional, only to keep your progress on a new phone.
• Other students only ever see a nickname and a built-in avatar.
• No chat, no public profiles, no contacts, no photos, no phone location.
• You can delete your account and data from inside the app at any time.

Free to start
Most of the app is free. Some features require Premium. Students without Premium see small banner ads and can choose to watch short videos for extra practice; ads never appear during a quiz or an exam.

Original content
Every lesson and question is written for Guul 12. We never copy textbook pages or past exam papers. Every question is solved a second time before it is published, and students can report any question they think is wrong.

Guul 12 is made by Kulmiss. It is an independent study app and is not affiliated with the Ministry of Education or the Educational Assessment and Examinations Service (EAES).
```

## Other fields

| Field | Value |
|---|---|
| App category | Education |
| Tags (pick up to 5) | Education, Exam prep, Test prep, Study |
| Contact email | **Owner** – the same address as `contact_email` in settings (QUESTIONS #39) |
| Website | `https://guul12.kulmiss.com` |
| Privacy policy | `https://guul12.kulmiss.com/privacy` |
| Account deletion URL (Data safety form) | `https://guul12.kulmiss.com/delete-account` |
| App icon | `app/tool/icon/play-store-512.png` (512 × 512, made by `make_icon.py`) |
| Feature graphic | `docs/store/feature-graphic.png` (1024 × 500, made by `tool/make_feature_graphic.py`) |
| Phone screenshots | See [screenshots.md](screenshots.md) |

## Rules these texts keep

- No price, discount, "free trial", payment method or link to payment anywhere (Google Play payments policy).
  "Some features require Premium" is the only Premium wording, and it does not say how to get it.
- No "best", "#1", "top" or other ranking words, no emoji or capitals for attention in the name (Play metadata policy).
- No claim of being official: the last line says the app is independent.
- Numbers must stay true: "more than 2,000 questions" (2,460 today, PROGRESS.md). Update if content shrinks.

Check the limits and banned words:

```bash
python3 docs/store/tool/check_listing.py
```
