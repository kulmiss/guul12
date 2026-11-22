# Android release builds

## Build settings (`--dart-define`)

| Name | What | Example |
|---|---|---|
| `API_BASE_URL` | Backend address, no trailing slash | `https://guul12.kulmiss.com` (default) |
| `GOOGLE_SERVER_CLIENT_ID` | The **Web** OAuth client ID from Google Cloud. Empty = Google sign-in stays off | `1234-abc.apps.googleusercontent.com` |
| `APP_VERSION` | Only if you build a version other than the one in `pubspec.yaml` | `1.0.1+2` |

The same Web client ID goes into the backend `.env` as `GOOGLE_CLIENT_IDS`.

```bash
cd app
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://guul12.kulmiss.com \
  --dart-define=GOOGLE_SERVER_CLIENT_ID=<web client id>
```

## Upload key (owner only, never in git)

1. Make the key once and keep two backups offline:
   ```bash
   keytool -genkey -v -keystore ~/guul12-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
   ```
2. Create `app/android/key.properties` (already in `.gitignore`):
   ```properties
   storeFile=/home/you/guul12-upload.jks
   storePassword=...
   keyAlias=upload
   keyPassword=...
   ```
3. Build. Without `key.properties` the release build is signed with the local debug key:
   good for testing on a phone, refused by Google Play.

Use Play App Signing in the Play Console, so Google keeps the app signing key and this is only the upload key.

## Rules the build keeps

- Permissions: internet only. No location, contacts, camera, microphone or exact alarms (a test checks the manifest).
- No price, buy button or payment link anywhere in the app (a test checks every UI text and string in the code).
- Backups of app data are off, so the sign-in token never leaves the phone.
