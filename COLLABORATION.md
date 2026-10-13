# Collaboration and Database Workflow

This project synchronizes two different kinds of changes:

- GitHub synchronizes application files, migrations, seeders, tests, and assets.
- A shared MySQL server synchronizes live records created, edited, or deleted through the application.

Do not commit `.env`, database passwords, SQLite database files, SQL dumps containing real data, `vendor`, or `node_modules`.

## Initial shared MySQL setup

Ask the database administrator for the shared development database host, port, database name, username, and password. Use a development or staging database, never the production database.

Each developer creates their own private `.env` file:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Configure the same shared database in each developer's `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=database-host-provided-by-the-administrator
DB_PORT=3306
DB_DATABASE=cfo_management
DB_USERNAME=your-individual-database-user
DB_PASSWORD=your-private-database-password
```

Use a separate database account for each developer when possible. Never send passwords through Git or place real credentials in `.env.example`.

Install the project and apply its current schema:

```powershell
composer install
npm ci
php artisan migrate
npm run build
php artisan test
```

Only one developer needs to seed an empty shared database:

```powershell
php artisan db:seed
```

During local development, the seeded demo accounts temporarily use `ChangeMe123!`. Set a private `CFO_DEMO_PASSWORD` before any real deployment. Do not repeatedly seed real or production data.

## Receiving code and database-structure updates

On Windows, run the repository's synchronization script from the project root:

```powershell
.\scripts\sync.ps1
```

The script refuses to continue when uncommitted work exists. It then downloads `main`, installs the exact dependencies, applies pending migrations, builds the frontend, and runs the tests.

The equivalent manual commands are:

```powershell
git pull --ff-only origin main
composer install --no-interaction --prefer-dist
npm ci
php artisan migrate
npm run build
php artisan test
```

When developers use the same shared MySQL database, live record changes are already visible to everyone. A Git pull is only needed for application files and migration code.

## Changing the database structure

Never make an unrecorded table change directly in phpMyAdmin. Create a Laravel migration so every environment receives the same schema change.

Examples:

```powershell
# Add a table
php artisan make:migration create_departments_table

# Change an existing table
php artisan make:migration add_phone_to_users_table --table=users

# Remove or rename a table or column
php artisan make:migration update_legacy_registration_fields
```

Edit the generated file in `database/migrations`, then verify it locally:

```powershell
php artisan migrate
php artisan test
npm run check
```

Commit the migration together with the code that depends on it:

```powershell
git add -A
git diff --cached --name-status
git commit -m "feat(database): describe the schema change"
git push origin main
```

After another developer runs `.\scripts\sync.ps1`, Laravel applies that migration automatically. Laravel records completed migrations, so the same migration is not applied twice.

Take a database backup before migrations that drop tables, remove columns, or transform important data. Do not use `php artisan migrate:fresh` on a shared database because it deletes every table.

## Changing shared records

When both developers connect to the same MySQL database:

- Records added through the application appear for both developers immediately.
- Updates and deletions are also shared immediately.
- No SQL file, Git commit, or pull is required for ordinary records.

For permanent reference data required in every environment, create a seeder instead of sharing a database dump:

```powershell
php artisan make:seeder DepartmentSeeder
php artisan db:seed --class=DepartmentSeeder
```

Write repeatable seeders with `updateOrCreate` or `updateOrInsert`, register them in `DatabaseSeeder`, and commit the seeder file. Other developers can then run `php artisan db:seed`.

## Publishing application-file changes

Synchronize before starting work:

```powershell
.\scripts\sync.ps1
```

After editing, verify and publish:

```powershell
composer check
npm run check
git add -A
git diff --cached --name-status
git commit -m "type(scope): describe the change"
git push origin main
```

Common commit labels include `feat`, `fix`, `test`, `docs`, `build`, `chore`, and `refactor`.

## Important limitations

- Git does not synchronize local MySQL databases or automatically execute committed `.sql` files.
- A shared MySQL database synchronizes database records, but not files stored on a developer's local disk.
- Uploaded documents currently use Laravel's local private storage. Sharing uploaded documents between computers requires shared object storage or a shared filesystem.
- GitHub Actions verifies migrations through the automated tests but does not modify the shared development database.
- If a migration fails, stop and investigate it. Do not edit an already-applied migration; create a new corrective migration.
