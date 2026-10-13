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

Open `.env` and replace `choose-a-strong-local-demo-password` with a private password. Then finish setup:

```powershell
php artisan migrate --seed
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

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

For the complete shared-database workflow, daily update commands, and rules for schema and data changes, read [COLLABORATION.md](COLLABORATION.md). Windows developers can update the code, dependencies, database schema, assets, and tests with one command:

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

| Role | University ID Number |
|---|---|
| Student | `2024-0001` or `2024-0002` |
| Admin | `CFO-001` |
| Super Admin | `ADM-001` |

All seeded accounts use the `CFO_DEMO_PASSWORD` value from `.env`.

## Main modules

- Role-based login and protected sessions
- Student profiles
- Activities and registrations
- Private document upload, download, and review
- Attendance checkpoints
- Facility requests with approved-booking conflict checks
- Announcements and notifications
- CSV participation reports
- Account management and audit history

Student files are stored outside the public web directory and served only after authorization. Production deployment still requires approved university identity procedures, backups, malware scanning, mail configuration, and privacy/security review.
