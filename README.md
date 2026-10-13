# Christian Formation Office Management System

Laravel 12 application for the University of La Salette, Inc. Christian Formation Office. It uses PHP, Blade HTML templates, CSS, JavaScript, and MySQL.

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel and MySQL
- Composer
- MySQL (the bundled XAMPP installation is supported)

## Local setup with XAMPP

1. Start **MySQL** in the XAMPP Control Panel.
2. Open phpMyAdmin and create a database named `cfo_management`.
3. Copy `.env.example` to `.env` if `.env` does not exist.
4. Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.
5. Set a private value for `CFO_DEMO_PASSWORD`.
6. Run:

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000`.

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
