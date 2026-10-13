$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectRoot

if (-not (Test-Path -LiteralPath '.git')) {
    throw 'This directory is not a Git repository.'
}

$pendingChanges = @(git status --porcelain)
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to inspect the Git working tree.'
}

if ($pendingChanges.Count -gt 0) {
    throw 'Uncommitted changes were found. Commit or stash them before synchronizing.'
}

git pull --ff-only origin main
if ($LASTEXITCODE -ne 0) { throw 'Git update failed.' }

composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { throw 'Composer dependency installation failed.' }

npm.cmd ci
if ($LASTEXITCODE -ne 0) { throw 'npm dependency installation failed.' }

php artisan migrate
if ($LASTEXITCODE -ne 0) { throw 'Database migration failed.' }

npm.cmd run build
if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }

php artisan test
if ($LASTEXITCODE -ne 0) { throw 'Automated tests failed.' }

Write-Host 'Synchronization completed successfully.' -ForegroundColor Green
