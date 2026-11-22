# Laravel Nova setup (owner task)

Nova is a paid package. The licence must **never** go into git (the repo is public).
Until Nova is installed, the backend, its tests and CI run without it:
`bootstrap/providers.php` loads `App\Nova\NovaServiceProvider` only when the `laravel/nova` package exists,
and Larastan skips the `backend/nova/` folder.

## 1. Your Nova licence

1. Log in at <https://nova.laravel.com> → **Licenses**.
2. Note your **account email** and create/copy a **licence key**.
3. Register the production domain on the licence: `guul12.kulmiss.com`.

## 2. Install on your PC (one time)

```bash
cd backend

# Store the credentials OUTSIDE the project (global composer auth), never in auth.json inside the repo.
composer config --global http-basic.nova.laravel.com YOUR_NOVA_EMAIL YOUR_NOVA_LICENCE_KEY

# Add the Nova package repository and package.
composer config repositories.nova composer https://nova.laravel.com
composer require laravel/nova:^5.0

php artisan nova:install        # publishes assets and config/nova.php
php artisan migrate
```

> `backend/.gitignore` already ignores `/auth.json` and `/.nova`. Check `git status` before you commit:
> only `composer.json`, `composer.lock`, `config/nova.php` and `public/vendor/nova` may change.

`nova:install` may create `app/Providers/NovaServiceProvider.php` and add it to `bootstrap/providers.php`.
**Delete that generated provider and undo the providers.php change** — our provider is `backend/nova/NovaServiceProvider.php`
and is already loaded automatically.

## 3. Create your super admin account

```bash
php artisan tinker
>>> $u = new App\Models\User;
>>> $u->student_id = app(App\Services\StudentIdGenerator::class)->generate();
>>> $u->role = App\Enums\UserRole::SuperAdmin;
>>> $u->name = 'Your name'; $u->email = 'you@kulmiss.com';
>>> $u->password = Illuminate\Support\Facades\Hash::make('a-long-password');
>>> $u->save();
```

Then open `/nova` and log in. Create helper accounts under **Staff** (roles: Payments helper, Content checker).

## 4. Let GitHub Actions install Nova (so CI tests Nova too)

In GitHub: **kulmiss/guul12 → Settings → Secrets and variables → Actions → New repository secret**:

| Name | Value |
|---|---|
| `NOVA_USERNAME` | your Nova account email |
| `NOVA_LICENSE_KEY` | your Nova licence key |

The backend workflow already uses them: when both secrets exist it runs
`composer config http-basic.nova.laravel.com "$NOVA_USERNAME" "$NOVA_LICENSE_KEY"` before `composer install`.
Secrets are never printed in logs and are not available to pull requests from forks.

## 5. On the server

Run the same `composer config --global http-basic...` line on the server (as the deploy user), then
`composer install --no-dev --optimize-autoloader`.

## 6. Tell me "Nova is installed"

Then I switch on the Nova checks: Larastan for `backend/nova/`, Nova feature tests (roles, Give Premium /
Undo Premium actions, student-ID search), and fix anything that does not work.

## What exists already (written, not yet tested against Nova)

| Area | Files |
|---|---|
| Provider, gate (staff only) | `nova/NovaServiceProvider.php` |
| Students (search by student ID, Give Premium) | `nova/Resources/Student.php`, `nova/Actions/GrantPremium.php` |
| Premium history (Undo Premium) | `nova/Resources/PremiumGrant.php`, `nova/Actions/RevokePremium.php` |
| Staff accounts and roles | `nova/Resources/Staff.php` |
| Content: subjects, units, chapters, questions, options, summaries, solved exercises, flashcards, passages | `nova/Resources/*.php` |
| Settings, feature switches, audit log | `nova/Resources/AppSetting.php`, `FeatureSwitch.php`, `AuditLog.php` |
| Dashboard (new students, Premium now) | `nova/Dashboards/Main.php`, `nova/Metrics/*` |
