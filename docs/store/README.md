# Google Play store pack

Everything needed to publish Guul 12 on Google Play. The owner does the Play Console steps;
these files hold the texts and answers, checked against the app's code.

| File | What |
|---|---|
| [listing-en.md](listing-en.md) | Main listing: name, short and full description, category, links |
| [listing-so.md](listing-so.md) | Somali listing – **draft for native review** |
| [listing-am.md](listing-am.md) | Amharic listing – **draft for native review** |
| [data-safety.md](data-safety.md) | Data safety form answers + advertising ID |
| [app-content.md](app-content.md) | Target audience, ads, content rating (IARC), other declarations |
| [reviewer-access.md](reviewer-access.md) | How the Play reviewer can see Premium features |
| [screenshots.md](screenshots.md) | Which 8 screenshots to take and what must never show |
| `feature-graphic.png` | 1024 × 500 feature graphic (made by `tool/make_feature_graphic.py`) |
| `tool/check_listing.py` | Checks text lengths and words Play does not allow (runs in CI) |

## Owner checklist (in order)

1. Google Play developer account for Kulmiss (organisation account; D-U-N-S number needed).
2. Make the upload key and `key.properties` (see `docs/android-release.md`). Turn on Play App Signing.
3. Set real values: contact email, WhatsApp number and Play link in Nova settings (QUESTIONS #39);
   AdMob IDs at build time; Google sign-in client IDs.
4. Build the AAB: `flutter build appbundle --release …` (see `docs/android-release.md`).
5. Create the app in Play Console: name "Guul 12: Grade 12 Exam Prep", default language English (United States),
   App, Free. ("Free" here only means no price on Play; Premium is arranged outside the app.)
6. Fill **App content** from [app-content.md](app-content.md) and [data-safety.md](data-safety.md).
7. Fill **Main store listing** from [listing-en.md](listing-en.md); add Somali and Amharic only after native review.
8. Upload icon, feature graphic and screenshots ([screenshots.md](screenshots.md)).
9. Set up the review account ([reviewer-access.md](reviewer-access.md)).
10. **Internal testing** first. A *personal* developer account created after 13 Nov 2023 must run a
    **closed** test with at least 12 testers opted in for 14 days in a row before it can ask for production.
    An organisation account does not have this rule, but a short closed test is still wise.
11. Countries: Ethiopia first (add others later if wanted).
12. Send for review. Answer any policy email within the deadline it gives.

## Keep in sync

If the app starts collecting new data, update **all three**: the privacy page
(`backend/resources/views/landing/privacy.blade.php`), [data-safety.md](data-safety.md) and the Play Console form.
