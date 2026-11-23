# Play Console – Data safety form

Play Console → Policy → App content → Data safety. These answers match the app as built and the
[privacy policy](https://guul12.kulmiss.com/privacy). If the app changes what it collects, update this file,
the privacy page and the form together.

The owner submits the form. You are responsible for the answers; this file is a careful draft based on the code.

## Overview questions

| Question | Answer | Why |
|---|---|---|
| Does your app collect or share any of the required user data types? | **Yes** | Study profile, activity, optional Google email, device IDs (AdMob). |
| Is all of the user data collected by your app encrypted in transit? | **Yes** | The app only talks to the API over HTTPS; the Google Mobile Ads SDK uses TLS. |
| Do you provide a way for users to request that their data is deleted? | **Yes** | In the app: Me → Delete my account. On the web: `https://guul12.kulmiss.com/delete-account`. |
| Does your app allow users to create an account? | **Yes** – method **OAuth** (Google sign-in) | Every install also gets an automatic anonymous account, so "no accounts" would be wrong. There is no username/password. |
| Delete account URL | `https://guul12.kulmiss.com/delete-account` | Required for apps with accounts. |

## Data types

"Collected" = sent from the phone to our server or to a library's server. "Shared" = given to another company
for its own use (Google AdMob counts; Anthropic for "Explain more" does **not**, because it gets only the
question text, no user data, and acts as our service provider).

| Category → type | Collected | Shared | Optional? | Purposes | What exactly |
|---|---|---|---|---|---|
| Personal info → **Name** | Yes | No | Optional | App functionality | Nickname, only if the student picks one (Google counts nicknames as names). |
| Personal info → **Email address** | Yes | No | Optional | App functionality, Account management | Only with Google sign-in. |
| Personal info → **User IDs** | Yes | No | Required | App functionality, Account management | Random student ID (ST-…), Google account ID if signed in. |
| Personal info → **Other info** | Yes | No | Optional | App functionality, Personalization | Gender (optional), grade, stream, target score. |
| Location → **Approximate location** | Yes | Yes | Required | App functionality (ours); Advertising, Fraud prevention (AdMob) | Region and city the student types (not GPS), and AdMob reads the IP address. |
| Financial info → **Purchase history** | Yes | No | Optional | App functionality | Premium plan and its dates. The payment itself happens outside the app. |
| App activity → **App interactions** | Yes | Yes | Required | App functionality, Analytics (ours); Advertising, Analytics (AdMob) | Answers, results, bookmarks, battles; AdMob records ad taps and views. |
| App activity → **Other user-generated content** | Yes | No | Optional | App functionality | Reports about questions (the text a student writes). |
| App info and performance → **Diagnostics** | Yes | Yes | Required | Analytics, Fraud prevention (AdMob) | Collected by the Google Mobile Ads SDK only. We use no crash or analytics SDK. |
| Device or other IDs → **Device or other IDs** | Yes | Yes | Required | App functionality (ours); Advertising, Analytics, Fraud prevention (AdMob) | A scrambled random install ID (ours); the Android advertising ID (AdMob). |

Everything else is **not collected**: contacts, precise location, photos, videos, audio, files, calendar,
messages, web history, health, fitness, installed apps, credit card or bank details, race, religion,
political views, sexual orientation.

Premium students never start the ads SDK, but the form is per app, so the AdMob rows stay.

## Check before submitting

1. Build the release AAB and open its merged manifest (Android Studio → Analyze APK → AndroidManifest.xml).
   It must list `com.google.android.gms.permission.AD_ID` (added by the ads library). Then answer
   Policy → App content → **Advertising ID**: "Yes, my app uses advertising ID", purpose **Advertising or marketing**.
2. Compare the AdMob rows with Google's page for the SDK version in `pubspec.lock`:
   <https://developers.google.com/admob/android/privacy/play-data-disclosure>. Google updates that page.
3. Make sure the privacy policy URL works on the live server before you submit.
