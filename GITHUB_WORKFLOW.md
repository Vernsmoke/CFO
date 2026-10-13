# Two-Developer GitHub Workflow

This guide is for two developers working on the CFO project. The repository owner maintains `main`, while both developers create branches, commit their work, and use pull requests to combine changes safely.

GitHub does not update either developer's computer automatically. Each developer must pull before starting work and after a pull request is merged.

## 1. Give the second developer access

The repository owner completes these steps on GitHub:

1. Open the `Vernsmoke/CFO` repository.
2. Open **Settings**, then **Collaborators**.
3. Select **Add people** and enter the developer's GitHub username.
4. Ask the developer to accept the invitation.

Repository write access allows the collaborator to push branches. Use pull requests so changes can be reviewed before they enter `main`.

## 2. Download the project for the first time

The second developer runs:

```powershell
cd C:\path\where\the\project\will\be\stored
git clone https://github.com/Vernsmoke/CFO.git
cd CFO

composer install
npm.cmd ci
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File -Force
php artisan key:generate
php artisan migrate --seed
npm.cmd run check
php artisan test
```

To use MySQL instead of SQLite, configure the private `.env` file as described in [COLLABORATION.md](COLLABORATION.md) before running migrations.

Run the application with two terminals:

```powershell
# Terminal 1
php artisan serve
```

```powershell
# Terminal 2
npm.cmd run dev
```

## 3. Start every work session by synchronizing

Before editing files, each developer runs:

```powershell
git switch main
git pull --ff-only origin main
```

The working tree should be clean. Check it with:

```powershell
git status
```

If `git status` lists uncommitted changes, commit or safely preserve them before pulling. Do not discard another developer's work.

After pulling project changes, synchronize dependencies and the database schema:

```powershell
composer install
npm.cmd ci
php artisan migrate
```

The project also provides a Windows synchronization script that performs these update and verification steps:

```powershell
.\scripts\sync.ps1
```

## 4. Create a branch for each task

Do not develop directly on `main`. Create a short, descriptive branch:

```powershell
git switch -c feature/activity-registration
```

Useful branch prefixes include:

- `feature/` for new functionality
- `fix/` for bug fixes
- `docs/` for documentation
- `refactor/` for internal code improvements

Examples:

```powershell
git switch -c fix/login-validation
git switch -c docs/setup-instructions
```

Tell the other developer which task you are handling. Avoid editing the same section of the same file at the same time when possible.

## 5. Save and publish a change

Review the changed files:

```powershell
git status
git diff
```

Stage only the files related to the task, then commit them with a clear label:

```powershell
git add path/to/changed-file.php path/to/another-file.blade.php
git diff --cached
git commit -m "feat(activities): improve activity registration"
```

Run verification before publishing:

```powershell
php artisan test
npm.cmd run check
```

Push the branch for the first time:

```powershell
git push -u origin feature/activity-registration
```

After the first push, additional commits on the same branch only need:

```powershell
git push
```

## 6. Create and merge a pull request

After pushing the branch:

1. Open the repository on GitHub.
2. Select **Compare & pull request** for the branch.
3. Confirm the base branch is `main`.
4. Describe what changed and how it was tested.
5. Create the pull request.
6. Wait for the automated checks to pass.
7. The repository owner reviews and merges the pull request.

Do not merge a pull request while required checks are failing.

## 7. Update after a pull request is merged

Both developers synchronize `main`:

```powershell
git switch main
git pull --ff-only origin main
composer install
npm.cmd ci
php artisan migrate
php artisan test
npm.cmd run check
```

Delete the completed local branch:

```powershell
git branch -d feature/activity-registration
```

Deleting the remote branch from GitHub after merging is also safe because its commits are already part of `main`.

## 8. Bring recent `main` changes into an unfinished branch

If the other developer's work is merged while your branch is still in progress:

```powershell
git switch main
git pull --ff-only origin main
git switch feature/activity-registration
git rebase main
```

If there are no conflicts, publish the updated branch:

```powershell
git push --force-with-lease
```

Use `--force-with-lease` only for your own feature branch after a rebase. Never force-push shared `main`.

## 9. Resolve a merge conflict during rebase

Git identifies the conflicting files. Open each file and choose the correct final content. Remove conflict markers such as `<<<<<<<`, `=======`, and `>>>>>>>`.

Then run:

```powershell
git add path/to/resolved-file
git rebase --continue
```

Repeat until the rebase completes, then run the tests and push the branch:

```powershell
php artisan test
npm.cmd run check
git push --force-with-lease
```

To cancel the rebase and return to the previous state:

```powershell
git rebase --abort
```

Ask the other developer before making uncertain conflict decisions.

## 10. Database changes

Git synchronizes migration and seeder files, not each developer's live local database.

When changing a table, create a Laravel migration:

```powershell
php artisan make:migration add_status_to_activities_table --table=activities
```

Edit and test the migration, then commit it with the code that uses it:

```powershell
php artisan migrate
php artisan test
git add database/migrations path/to/related-code
git commit -m "feat(database): add activity status"
```

After pulling that commit, the other developer runs:

```powershell
php artisan migrate
```

Never commit `.env`, real passwords, production data, or SQL exports containing private records. Never run `php artisan migrate:fresh` against a shared database because it deletes every table.

## Command summary

Start work:

```powershell
git switch main
git pull --ff-only origin main
git switch -c feature/describe-the-task
```

Publish work:

```powershell
git status
git add path/to/changed-files
git commit -m "type(scope): describe the change"
php artisan test
npm.cmd run check
git push -u origin feature/describe-the-task
```

Update after a merge:

```powershell
git switch main
git pull --ff-only origin main
composer install
npm.cmd ci
php artisan migrate
```

## Team rules

- Pull before starting work.
- Create one branch for each task.
- Keep commits focused and clearly labeled.
- Use pull requests to update `main`.
- Wait for automated checks before merging.
- Never force-push `main`.
- Never commit `.env` or credentials.
- Use Laravel migrations for every database-structure change.
- Communicate before changing the same files or resolving conflicts.
