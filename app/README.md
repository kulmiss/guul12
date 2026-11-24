# Guul 12 app (Flutter)

Offline-first exam-prep app. See the main [README](../README.md) for how to run it and
[docs/android-release.md](../docs/android-release.md) for release builds.

**Needs Flutter 3.50.1 or newer (Dart 3.14).** CI uses exactly 3.50.1. Older versions stop at
`flutter pub get` with "requires SDK version ^3.14.0". Fix: `flutter channel stable`, then `flutter upgrade`.

| Folder | What |
|---|---|
| `lib/data` | API client, phone database (sqflite), repository (offline-first sync) |
| `lib/domain` | Scoring, practice sets, practice session, Ethiopian calendar (pure Dart, fully tested) |
| `lib/app` | Providers (Riverpod), router (go_router), app widget |
| `lib/ui` | Theme (Sunrise), screens, question widgets |
| `lib/l10n` | UI texts (`app_en.arb`); no text is hard-coded in widgets |
| `assets/hook` | The 5-question warm-up per grade used during onboarding (original questions) |
| `test/golden/goldens` | Screenshots of key screens (phone + tablet, light + dark) |
