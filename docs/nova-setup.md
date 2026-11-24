# Laravel Nova setup (owner)

Nova is a paid package. The licence and the Nova package itself must **never** go into git (the repo is public).
Without Nova, the backend, its tests and CI still run: `bootstrap/providers.php` loads
`App\Providers\NovaServiceProvider` only when the `laravel/nova` package exists, and Larastan leaves out
`app/Nova` (see `backend/phpstan-nova.php`).

**Status (2026-11-23): Nova 5.7.6 is installed on the owner's PC, so the Nova code is ON.**
It is tested with that copy: Larastan level 8 on `app/Nova` and the `Nova` test suite (11 tests: who gets in,
which role sees what, find a student by ID + Give/Undo Premium, create staff, and every dashboard card: who sees it
and that its number is right, on SQLite and MySQL).

## Where things are

| What | Where | In git? |
|---|---|---|
| Our Nova screens (resources, actions, metrics, dashboard) | `backend/app/Nova/` | yes |
| Our Nova provider (gate: staff only, resource list) | `backend/app/Providers/NovaServiceProvider.php` | yes |
| Nova tests | `backend/tests/Nova/` (`php artisan test --testsuite=Nova`) | yes |
| The Nova package (your download) | `backend/nova/` (composer path repository) | **no** (ignored) |
| Nova assets | `backend/public/vendor/nova/` | **no** (ignored) |
| Licence key | global composer auth or `auth.json` | **no** (ignored) |

## Your PC (already done)

`C:\laragon\www\guul12\nova` holds the package, and your `composer.json` has:

```json
"repositories": [{ "type": "path", "url": "nova" }],
"require": { "laravel/nova": "*" }
```

The `composer.json` and `composer.lock` I copy to your PC already contain these lines (the ones in git do not,
so CI works without the licence). After each sync run `composer install`.

## Let GitHub Actions test Nova too

In GitHub: **kulmiss/guul12 → Settings → Secrets and variables → Actions → New repository secret**:

| Name | Value |
|---|---|
| `NOVA_USERNAME` | your Nova account email |
| `NOVA_LICENSE_KEY` | your Nova licence key |

When both exist, the backend workflow installs `laravel/nova` from nova.laravel.com, runs Larastan on the Nova
code and runs the Nova test suite. Secrets are never printed and are not given to pull requests from forks.

## Create your super admin account

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

## On the server (Phase 5)

Either copy the `nova/` folder next to the app (same path repository), or use the official repository:
`composer config --global http-basic.nova.laravel.com EMAIL KEY` and
`composer config repositories.nova composer https://nova.laravel.com && composer require laravel/nova:^5.7`.
Register the production domain `guul12.kulmiss.com` on the licence.

## What Nova shows

| Area | Files |
|---|---|
| Provider, gate (staff only) | `app/Providers/NovaServiceProvider.php` |
| Students (search by student ID, Plan badge, Give Premium) | `app/Nova/Resources/Student.php`, `app/Nova/Actions/GrantPremium.php` |
| Premium history (Undo Premium, red "destructive" button) | `app/Nova/Resources/PremiumGrant.php`, `app/Nova/Actions/RevokePremium.php` |
| Staff accounts and roles | `app/Nova/Resources/Staff.php` |
| Content: subjects, units, chapters, questions, options, summaries, solved exercises, flashcards, passages, reports | `app/Nova/Resources/*.php` |
| Settings, feature switches, audit log | `app/Nova/Resources/AppSetting.php`, `FeatureSwitch.php`, `AuditLog.php` |
| Dashboard | `app/Nova/Dashboards/Main.php`, `app/Nova/Metrics/*` |

### Dashboard cards

| Card | Who sees it | What it shows |
|---|---|---|
| New students | all staff | Accounts created (today / 7 / 30 days / month) |
| Active students | all staff | Students who used the app in the range |
| Answers given | all staff | Answers that reached the server in the range |
| Premium students now | super admin, payments helper | Students with Premium today |
| Open question reports | super admin, content checker | Reports nobody has checked yet |
| AI spend ("Explain more") | super admin | This month's spending against `ai_monthly_cap_usd` |
| System health | super admin | The watchdog checks, live: database, storage, backup age, disk, AI budget |

A card a role cannot see also cannot be opened by its address.
