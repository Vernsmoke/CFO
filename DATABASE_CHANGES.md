# Database Change Guide and Log

This file has two purposes:

1. Explain how both developers safely make and receive database changes.
2. Keep a human-readable record of every shared schema or required-data change.

Update this file in the same branch and commit as every database migration or seeder change.

## What must be recorded

Add a changelog entry when a change affects either developer's database, including:

- Creating, renaming, or removing a table
- Adding, changing, renaming, or removing a column
- Adding or changing an index, unique constraint, or foreign key
- Changing required reference or demonstration data in a seeder
- Running a controlled data transformation required by the application

Do not record ordinary local test records, such as a student registration created while testing. Because the database is not hosted, those records stay on the computer where they were created.

## Important behavior

Each developer has a separate local database. GitHub synchronizes migration and seeder files, but it does not synchronize live database contents.

| Type of change | How it reaches the other developer |
|---|---|
| Table, column, index, or constraint | Commit a Laravel migration; the other developer runs `php artisan migrate` |
| Required reference or demo data | Commit an idempotent seeder; the other developer runs `php artisan db:seed` |
| Ordinary records entered through the application | Not synchronized |
| Exact one-time database copy | Use a sanitized export outside Git; import it manually |
| Uploaded documents | Not synchronized by Git or database migrations |

Never commit `.env`, database passwords, private student information, production data, or database dumps containing real records.

## Before making a database change

Synchronize the project and create a branch:

```powershell
git switch main
git pull --ff-only origin main
git switch -c feature/describe-database-change
```

Check which migrations have already been applied:

```powershell
php artisan migrate:status
```

Back up important data before any destructive migration. Never run `php artisan migrate:fresh` on a shared or important database because it deletes every table.

## Change a table or column

Do not make an unrecorded structural change only through phpMyAdmin. Create a Laravel migration instead.

Create a new table:

```powershell
php artisan make:migration create_departments_table
```

Change an existing table:

```powershell
php artisan make:migration add_status_to_activities_table --table=activities
```

Rename or remove fields with a new migration:

```powershell
php artisan make:migration update_legacy_activity_fields --table=activities
```

Edit the generated file in `database/migrations`. The `up()` method applies the change, and the `down()` method should safely reverse it whenever practical.

Apply and verify the change:

```powershell
php artisan migrate
php artisan migrate:status
php artisan test
npm.cmd run check
```

Add an entry to the changelog at the bottom of this file before committing.

## Change required or demonstration data

Create a seeder for data that every development database requires:

```powershell
php artisan make:seeder DepartmentSeeder
```

Write seeders so they can safely run more than once. Prefer `updateOrCreate`, `updateOrInsert`, or stable unique keys instead of unconditional inserts.

Apply and verify the seeder:

```powershell
php artisan db:seed --class=DepartmentSeeder
php artisan test
```

Register the seeder in `database/seeders/DatabaseSeeder.php` when it should run as part of the normal project seed process. Never place real credentials or personal information in a seeder.

## Commit and publish a database change

Stage the migration or seeder, related application code, tests, and this changelog explicitly:

```powershell
git status
git add database/migrations database/seeders app resources tests DATABASE_CHANGES.md
git diff --cached
git commit -m "feat(database): describe the database change"
php artisan test
npm.cmd run check
git push -u origin feature/describe-database-change
```

Create a pull request into `main`. Explain the migration's effect, whether it is destructive, and any command the other developer must run.

## Receive another developer's database change

After the pull request is merged:

```powershell
git switch main
git pull --ff-only origin main
composer install
npm.cmd ci
php artisan migrate
php artisan db:seed
php artisan test
npm.cmd run check
```

Read the newest changelog entry before migrating. `php artisan migrate` applies only migrations that have not already run.

Run `php artisan db:seed` only when the changelog says required seed data changed. Seeders committed to this project must be safe to repeat.

## Correct a migration problem

If a migration has already been pushed or applied by another developer, do not edit it to change history. Create a new corrective migration:

```powershell
php artisan make:migration correct_activity_status_definition --table=activities
```

If a new local migration fails before it is shared, inspect the error and your database state. Use rollback only when you understand which migration will be reversed:

```powershell
php artisan migrate:status
php artisan migrate:rollback --step=1
```

Do not use rollback on a shared database without coordinating with the other developer.

## Sharing a one-time local MySQL snapshot

A database export is optional and should not replace migrations. Use it only when another developer needs the same sanitized test records.

Export:

```powershell
mysqldump -u root -p cfo_management > cfo_development.sql
```

Import into an empty or backed-up local database:

```powershell
mysql -u root -p cfo_management < cfo_development.sql
```

Keep the SQL file outside the repository unless it contains only reviewed, fictional, non-sensitive data and the team has explicitly agreed to track it.

## Changelog entry template

Copy this template to the top of the **Change history** section for every shared database change:

```markdown
### YYYY-MM-DD — Short change title

- **Developer:** GitHub username or name
- **Type:** Schema / Seeder / Data transformation
- **Status:** Proposed / Merged / Applied
- **Files:** `database/migrations/example.php`
- **Summary:** What changed and why.
- **Existing-data impact:** None, backfilled, transformed, or deleted.
- **Required command:** `php artisan migrate`
- **Rollback/backup notes:** How to reverse it and whether a backup is required.
- **Pull request or commit:** Link or commit hash when available.
```

## Change history

Add the newest entry first.

### 2026-10-14 — Document current database baseline

- **Developer:** Project team
- **Type:** Schema and seeder baseline
- **Status:** Applied
- **Files:** `0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`, `2026_10_13_000100_create_cfo_tables.php`, and `database/seeders/DatabaseSeeder.php`
- **Summary:** The project currently defines users, cache, jobs, profiles, activities, registrations, documents, attendance, facility requests, announcements, notifications, audit logs, and settings. The main seeder creates fictional development accounts, a student profile, sample activities, an announcement, and the office-name setting.
- **Existing-data impact:** This entry documents the existing baseline and does not modify data.
- **Required command:** New installations run `php artisan migrate --seed`; existing installations run `php artisan migrate`.
- **Rollback/backup notes:** No rollback is required for this documentation entry. Back up important data before reversing baseline migrations.
- **Pull request or commit:** To be added when this guide is committed.
