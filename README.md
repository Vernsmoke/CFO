# Christian Formation Office Management System

Laravel 12 application for the University of La Salette, Inc. Christian Formation Office. It uses PHP, Blade HTML templates, CSS, JavaScript, and SQLite or MySQL.

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel
- Composer
- Node.js 22 or newer with npm
- SQLite for the quickest local setup, or MySQL through XAMPP

## Fresh-clone setup

After cloning or pulling the repository, run these commands from the project directory:

```powershell
composer install
npm ci
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File -Force
php artisan key:generate
```

The local demo accounts temporarily use `ChangeMe123!`. Replace `CFO_DEMO_PASSWORD` before any real deployment. Then finish setup:

```powershell
php artisan migrate --seed
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

Student self-registration uses the institution-approved Department → Course → Major catalog in `config/student_registration.php`. Year level and section are constrained to one digit and one uppercase letter. Verification and password-reset messages use Laravel mail; configure `MAIL_MAILER=smtp` and the approved SMTP credentials for delivery to real inboxes. The default local `log` mailer writes messages to the application log for development.

Existing collaborators should run the following after syncing changes:

```powershell
git pull origin main
composer install
npm ci
php artisan migrate
npm run build
php artisan test
```

Do not copy another developer's `.env`, database, `vendor`, or `node_modules`. Those are intentionally excluded from Git.

For the two-developer branch, pull-request, and conflict-resolution process, read [GITHUB_WORKFLOW.md](GITHUB_WORKFLOW.md). For the shared-database workflow, read [COLLABORATION.md](COLLABORATION.md). Record every shared migration or seeder update in [DATABASE_CHANGES.md](DATABASE_CHANGES.md).

Windows developers can update the code, dependencies, database schema, assets, and tests with one command:

```powershell
.\scripts\sync.ps1
```

## Using MySQL with XAMPP

1. Start MySQL in the XAMPP Control Panel.
2. Create a database named `cfo_management` in phpMyAdmin.
3. Change the database section of `.env` to the commented MySQL example in `.env.example`.
4. Run `php artisan migrate --seed`.

## Automated verification

GitHub Actions runs the PHP tests and production frontend build for every push and pull request to `main`. A red check means collaborators should not sync or deploy that revision until it is fixed.

The seed command creates fictional accounts for local testing:

| Role | University ID Number | Email login |
|---|---|---|
| Student | `2024-0001` | `alex@example.invalid` |
| Student | `2024-0002` | `jamie@example.invalid` |
| Admin | `CFO-001` | Not available |
| Super Admin | `ADM-001` | Not available |

All seeded accounts use the `CFO_DEMO_PASSWORD` value from `.env`. The tracked
`.env.example` sets the public local-demo password to `ChangeMe123!`, so a fresh
clone receives the same credential after copying `.env.example` to `.env` and
running `php artisan migrate --seed`. Never use this demo password in production.

## Main modules

- Role-based login and protected sessions
- Student self-registration with pending status, single-use email verification, verified-email login, resend cooldown, and password recovery
- Student profiles
- Exact course/year/section activity distribution with reviewed recipient counts
- Activity-specific Recollection and Retreat forms, payment receipts, and Mass camera/reflection submissions
- Private document upload, download, and review
- Camera-based CFO Staff attendance with Super Admin monitoring
- Facility requests with approved-booking conflict checks
- Announcements and notifications
- CSV participation reports
- Account management and audit history

Student files are stored outside the public web directory and served only after authorization. Production deployment still requires approved university identity procedures, backups, malware scanning, mail configuration, and privacy/security review.
