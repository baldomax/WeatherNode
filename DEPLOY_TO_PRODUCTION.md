# Production Deployment Guide

Simple step-by-step guide to deploy updates to production.

## Quick Start

1. **Build assets** (if needed): `npm run build`
2. **Backup** production (database + .env)
3. **Get file list** from dev: `php artisan deploy:list-files`
4. **Upload files** to production (including `public/build/`)
5. **Run migration**: `php artisan migrate`
6. **Update .env** with new settings
7. **Clear caches**: `php artisan optimize:clear`

---

## ⚠️ Files to NEVER Upload

These folders contain dev-only or environment-specific data:

| Folder/File | Why NOT to upload |
|-------------|-------------------|
| `bootstrap/cache/*` | Contains cached service providers from dev (causes "class not found" errors) |
| `vendor/` | Contains dev-only packages. Use `composer install --no-dev` on production instead |
| `storage/logs/*` | Dev log files |
| `storage/framework/cache/*` | Dev cache data |
| `storage/framework/sessions/*` | Dev sessions |
| `storage/framework/views/*` | Compiled views (regenerate on production) |
| `storage/app/radar-tiles/*` | Cached radar tiles |
| `.env` | Contains dev settings - production has its own |
| `node_modules/` | Not needed on production |

**If you accidentally uploaded `bootstrap/cache/` or `vendor/`:**
```bash
# On production server
rm -f bootstrap/cache/*.php
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

---

## When to Build Assets

Run `npm run build` on your dev machine **before uploading** if you changed:

| Changed | Build needed? |
|---------|---------------|
| `resources/css/*.css` | ✅ Yes |
| `resources/js/*.js` | ✅ Yes |
| `tailwind.config.js` | ✅ Yes |
| Blade templates with **new** Tailwind classes | ✅ Yes |
| Blade templates (only PHP/HTML changes) | ❌ No |
| Controllers, Models, Routes | ❌ No |
| Database migrations | ❌ No |
| Config files | ❌ No |
| Translations (`lang/*.json`) | ❌ No |

**When in doubt:** just run `npm run build` - it takes 2 seconds.

**Important:** Your production server does NOT need npm/node. The compiled files are uploaded via `public/build/`.

---

## Step 1: Backup (2 minutes)

**On production server:**

```bash
# Backup database
mysqldump -u [username] -p [database_name] > backup_$(date +%Y%m%d).sql

# Backup .env
cp .env .env.backup_$(date +%Y%m%d)
```

## Step 2: Get Files to Deploy (1 minute)

**On your dev machine:**

```bash
php artisan deploy:list-files
```

This shows all files that need to be uploaded. Copy the list or use the `--package` option to save it.

## Step 3: Upload Files (5-10 minutes)

Upload all files from the list to production, keeping the same folder structure.

**Don't forget:** If you ran `npm run build`, also upload the `public/build/` folder!

**Quick method (if you have SSH):**
```bash
# From dev machine, upload entire directories
scp -r app/Services/Update/ user@production:/path/to/app/Services/
scp app/Models/UpdateLog.php user@production:/path/to/app/Models/
scp -r public/build/ user@production:/path/to/public/build/
# ... (repeat for all files)
```

**Or use FTP/SFTP** to upload files manually.

## Step 4: Run Migration (30 seconds)

**On production server:**

```bash
php artisan migrate
```

This creates the `update_logs` table.

## Step 5: Update .env (1 minute)

**On production, add to `.env`:**

```env
UPDATER_ENABLED=false
UPDATER_GITHUB_REPO=centauri/WeatherNode
```

(Set `UPDATER_ENABLED=false` for now - enable it later when ready)

## Step 6: Clear Caches (10 seconds)

**On production server:**

```bash
php artisan optimize:clear
```

## Step 7: Verify (1 minute)

Visit `/admin/settings/updates` - should load without errors.

---

## Troubleshooting

**"Class Laravel\Pail\PailServiceProvider not found" error?**

This happens when `bootstrap/cache/` files from dev were uploaded to production.
Laravel Pail is a dev-only package that isn't installed on production.

```bash
# Fix on production server:
rm -f bootstrap/cache/*.php
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
```

If composer is not available on your server, run this on dev:
```bash
composer dump-autoload --no-dev --optimize
```
Then upload these files to production:
- `vendor/composer/autoload_*.php`
- `vendor/autoload.php`

---

**"Target class [UpdateController] does not exist" error?**
1. Verify file exists: `ls -la app/Http/Controllers/Admin/UpdateController.php`
2. If missing, upload the file from dev
3. Run: `composer dump-autoload` on production
4. Clear caches: `php artisan optimize:clear`

**Migration error?**
- Check if table exists: `php artisan tinker` → `\Schema::hasTable('update_logs')`
- If `true`, migration already ran - skip it

**Routes not found?**
- Run: `php artisan route:clear`

**Class not found (general)?**
- Run: `composer dump-autoload` on production
- Verify all files from `php artisan deploy:list-files` were uploaded
- Check you didn't upload `bootstrap/cache/` from dev

**Radar shows 508 "Loop Detected" errors?**

This happens on shared hosting when the radar tile proxy is enabled. Shared hosts have security rules that detect the many rapid tile requests as a potential attack or loop.

Solution: Disable the proxy in Admin → Settings → Radar → "Use server-side tile caching" = OFF

Or via command line:
```bash
php artisan tinker --execute="\App\Models\Setting::updateOrCreate(['key' => 'radar.use_proxy'], ['value' => '0']);"
```

Note: The tile proxy feature is recommended for VPS/dedicated servers only. On shared hosting, use direct RainViewer access (proxy disabled).

**Permission errors?**
- Make sure `storage/` and `bootstrap/cache/` are writable

---

## Rollback (if needed)

1. Restore database: `mysql -u [user] -p [db] < backup_[date].sql`
2. Restore .env: `cp .env.backup_[date] .env`
3. Remove uploaded files
4. Clear caches: `php artisan optimize:clear`
