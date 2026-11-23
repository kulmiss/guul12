# App access – instructions for the Google Play reviewer

Play Console → Policy → App content → App access → **All or some functionality is restricted** → Add instructions.

Most of the app works without any sign-in. Premium items show "🔒 Premium". So the reviewer can check them,
give them a Google account that already has Premium.

## Owner setup (once, before the first review)

1. Create a new Google account only for reviews, for example `guul12.review@gmail.com`.
   Use a strong password and keep it in your password manager. **Never put it in git or in a chat.**
2. Install the app (internal testing track), open Me → Sign in with Google, and sign in with that account.
3. Note the Student ID shown on Me (like ST-7K3Q9).
4. In Nova → Students, find that Student ID → **Give Premium** → plan "Year", payment reference `REVIEW`.
5. Restart the app: Me shows "Premium until …" and there are no ads.
6. Before each new release, check the Premium end date and extend it if needed.

## Text to paste in Play Console (fill the two blanks there, not here)

```text
No sign-in is needed for most of the app: open it and pick a grade and stream.

To see Premium features (all mock exams, personal exams, "Explain more", no ads):
1. Open the Me tab.
2. Tap "Sign in with Google".
3. Sign in with the Google account below. Premium turns on automatically.

Username: <review Google account>
Password: <its password>

The account has no two-step verification so you can sign in on any device. Students arrange Premium outside the app; there is no purchase inside the app.
```

Google asks for working credentials that do not expire. Turn **off** 2-step verification on this one review
account only, or Google may block the reviewer's sign-in from a new device.

## Needed first

- Google sign-in must work in the release build: `GOOGLE_SERVER_CLIENT_ID` at build time and
  `GOOGLE_CLIENT_IDS` on the server (see `docs/android-release.md`), and the feature switch `google_sign_in` on.
