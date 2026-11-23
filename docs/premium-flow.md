# Premium flow (payment outside the app)

Google Play allows this because the app has **no price, no buy button and no link or text that
leads to payment**. Locked items only show "🔒 Premium". Prices and how to pay live on the landing
page (WhatsApp / email), which the app never links to.

| Step | Who | Where |
|---|---|---|
| 1. Student finds the price and how to pay | Student | Landing page (Phase 5), not the app |
| 2. Student pays and sends their **Student ID** (Me → "Your student ID", e.g. `ST-7K3Q9`) and the payment reference | Student | WhatsApp or email |
| 3. Check the payment, open **Students**, search the Student ID, run **Give Premium** (plan + payment reference) | Payments helper or super admin | Nova |
| 4. The app picks it up at the next start, return to the app or pull-to-refresh: ads stop, Premium items open, Me shows the end date | — | App |
| 5. Mistake or refund: **Undo Premium** on the grant (asks for a reason) | Payments helper or super admin | Nova |

Every grant and undo is written to the audit log with who did it.

## What the student sees

- Me: "Premium until <date>" (no price).
- Home, 7 days before the end: "Your Premium ends on <date>."; for 7 days after: "Your Premium ended on <date>."
  Neutral text only: no instructions, no price, no link (a test checks for payment words).
- Free students: "🔒 Premium" on locked items, and "Explain more · 🔒 Premium".

## Other ways to get Premium days

- **Referral** (settings in Nova): a friend who really studies gives the inviter Premium days
  (`referral_premium_days`, `0` = off).
- No other in-app way. Rewarded videos only unlock one more random exam for the day.
