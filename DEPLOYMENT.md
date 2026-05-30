# Deployment guide

This guide explains how to deploy WeatherNode to a production web server.
For local development, see DEVELOPMENT.md.

If you are deploying for the first time, follow the steps in order.
If you are on shared hosting without server-side npm, start with [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md).

## First-time install paths

### Path A (shared hosting, no Node.js on server)

Most shared hosts do not allow npm builds. In that case:

1. On the server, clone and initialize:
   ```bash
   git clone https://github.com/centauri/WeatherNode.git
   cd WeatherNode
   git fetch --tags
   TAG="$(git tag --sort=-v:refname | head -n 1)"
   git checkout "$TAG"
   composer install --no-dev --optimize-autoloader --no-scripts
   cp .env.example .env
   php artisan key:generate
   # edit .env with database settings
   php artisan migrate --force
   php artisan package:discover
   php artisan db:seed
   php artisan admin:create
   ```
2. On the server, fetch prebuilt assets from the matching release tag:
   ```bash
   git fetch --tags
   TAG="$(git tag --sort=-v:refname | head -n 1)"
   git checkout "$TAG"
   curl -L -o /tmp/weathernode-deploy.zip "https://github.com/centauri/WeatherNode/releases/download/${TAG}/weathernode-deploy.zip"
   rm -rf /tmp/weathernode-release
   mkdir -p /tmp/weathernode-release
   unzip -o /tmp/weathernode-deploy.zip "public/build/*" -d /tmp/weathernode-release
   rm -rf public/build
   cp -R /tmp/weathernode-release/public/build public/build
   ```
3. Verify assets:
   ```bash
   ls public/build/manifest.json
   ```

### Path B (server has Node.js/npm)

Use the normal flow in this guide and run `npm install && npm run build` on the server.

## Quick fix for common errors

If you're seeing these errors after uploading files:

### "Table 'database.settings' doesn't exist"
### "npm: command not found"
### "Script @php artisan package:discover returned error code 1"

**Quick solution, run these commands in order:**

```bash
# 1. Install PHP dependencies (skip scripts that need database)
composer install --no-dev --optimize-autoloader --no-scripts

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Edit .env with your database credentials, then run migrations
php artisan migrate --force

# 4. Complete package discovery
php artisan package:discover

# 5. Seed settings and create first admin
php artisan db:seed
php artisan admin:create

# 6. For npm, choose one:
#    A) If you can install Node.js on server (see Troubleshooting section)
#    B) Fetch prebuilt assets from GitHub release (no npm required)
#    C) Build assets locally and upload public/build/ directory
```

**No npm fallback:** If your hosting doesn't support Node.js and you do not want to use npm locally:
```bash
git fetch --tags
TAG="$(git tag --sort=-v:refname | head -n 1)"
git checkout "$TAG"
curl -L -o /tmp/weathernode-deploy.zip "https://github.com/centauri/WeatherNode/releases/download/${TAG}/weathernode-deploy.zip"
rm -rf /tmp/weathernode-release
mkdir -p /tmp/weathernode-release
unzip -o /tmp/weathernode-deploy.zip "public/build/*" -d /tmp/weathernode-release
rm -rf public/build
cp -R /tmp/weathernode-release/public/build public/build
```

## Requirements

- PHP 8.2 or newer, with extensions: `pdo`, `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `xml`, `curl`, `zip`, `gd`, `fileinfo`.
- Composer (PHP dependency manager).
- A database, SQLite or MySQL.
- A web server (Apache/Nginx) configured to point to the public directory.
- Cron access for scheduled tasks.
- Node.js and npm only if you plan to build assets on the server.

## Files you should not upload

- .git
- .gitignore
- node_modules (will be regenerated)
- vendor (will be regenerated via composer install)
- .env (create new one on server)
- public/hot (development file)
- public/storage (symlink, will be created)
- storage/logs/*.log
- bootstrap/cache

You can upload a built public/build directory, or build it on the server.

## Step 1, get the code on the server

**Option A, Git (recommended)**

```bash
git clone <your GitHub clone URL>
cd WeatherNode
```

**Option B, upload a tar archive**

Create an archive on your local machine, then upload it and extract it on the server.

```bash
tar --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='.env' \
    --exclude='storage/logs/*.log' \
    -czf weathernode.tar.gz .
```

Upload the archive to your server via FTP, SFTP, or SCP. Then extract it:

```bash
tar -xzf weathernode.tar.gz
cd WeatherNode
```

## Step 2, install dependencies

```bash
# Install PHP dependencies (production only, no dev tools)
composer install --no-dev --optimize-autoloader

# If Node.js/npm is available on the server:
npm install
npm run build

# If Node.js/npm is NOT available on the server:
# fetch prebuilt assets from GitHub release (see Path A / quickstart).
```

If composer scripts fail and you need to finish setup first, use this sequence.

```bash
composer install --no-dev --optimize-autoloader --no-scripts
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan package:discover
```

## Step 3, configure environment

Copy the example file and generate the app key.

```bash
cp .env.example .env
php artisan key:generate
```

Set at least these values in .env.

```env
APP_NAME=WeatherNode
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

# Session security (HTTPS production). Cookies only over TLS, session
# payload encrypted at rest, SameSite=Lax.
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_SAME_SITE=lax

# This app is served over HTTPS directly (no reverse proxy). Do NOT configure
# trusted proxies — leaving them off prevents clients from spoofing their IP via
# X-Forwarded-For (which would otherwise defeat rate limiting and visitor logs).

# Database (SQLite or MySQL)
DB_CONNECTION=sqlite
DB_DATABASE=/full/path/to/database/database.sqlite

# Or MySQL
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=weathernode
# DB_USERNAME=your_user
# DB_PASSWORD=your_password

# Optional: GeoIP for visitor analytics
# MAXMIND_LICENSE_KEY=your_license_key

# Optional automation only:
# If set, `db:seed` can bootstrap the first admin when no admin exists yet.
# You can leave these unset and run `php artisan admin:create` after seeding.
# ADMIN_EMAIL=admin@example.com
# ADMIN_PASSWORD=change-this-password
```

### Mail configuration

**Modern method (recommended):** Configure via Admin > Settings > Mail after deployment.

1. Go to Admin > Settings > Mail.
2. Choose your provider:
   - **OAuth2** (Gmail/Microsoft): Enter Client ID and Secret, click Authorize.
   - **Predefined SMTP**: Brevo, Mailjet, Postmark, Mailgun, SMTP2Go.
   - **Custom SMTP**: Any other SMTP server.
3. Test email sending from the admin UI.

OAuth2 token refresh is automatic. Gmail and Microsoft require OAuth2 (app passwords are deprecated).

**Legacy .env method (still supported):**

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@example.com
MAIL_FROM_NAME="WeatherNode"
```

## Step 4, database setup

```bash
# Run migrations
php artisan migrate --force

# Seed application settings
php artisan db:seed

# Create first admin account (interactive)
php artisan admin:create
```

**Admin user setup:**

`admin:create` is the primary first-time setup flow and does not require storing a password in `.env`.
For automation (CI/provisioning), you can run:

```bash
php artisan admin:create --email=admin@example.com --password="strong-password" --name="Administrator"
```

If a matching non-admin user already exists, add `--promote` to elevate that account.
If an admin already exists for that email, the command exits without modifying the account.

### Migrating from SQLite to MySQL

If you're moving from a local SQLite database to MySQL on the server:

```bash
# 1. Ensure MySQL is configured in .env (DB_CONNECTION=mysql etc.)
# 2. Run migrations to create MySQL tables
php artisan migrate --force

# 3. Copy your SQLite database file to the server, then run:
php artisan db:migrate-to-mysql --sqlite-path=/path/to/database.sqlite

# Or if your SQLite file is in the default location:
php artisan db:migrate-to-mysql
```

What the migration command does:
- Reads all data from your SQLite database
- Converts data types automatically (timestamps, JSON, etc.)
- Inserts data into MySQL tables in chunks (100 records at a time)
- Preserves all existing data: settings, weather readings, users, etc.
- Handles errors gracefully (skips problematic records with warnings)

**Alternative: Manual export/import**

If the automated command fails or you prefer manual control:

```bash
# Export SQLite data
sqlite3 database.sqlite .dump > dump.sql

# Convert SQL syntax (SQLite and MySQL differ slightly):
# - Remove SQLite-specific syntax
# - Convert INTEGER PRIMARY KEY to AUTO_INCREMENT
# - Adjust timestamp formats if needed

# Import to MySQL
mysql -u username -p database_name < dump.sql
```

The automated command (`db:migrate-to-mysql`) is recommended as it handles all conversions automatically.

## Step 5, storage link and permissions

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # Linux
# OR
chown -R _www:_www storage bootstrap/cache           # macOS
```

Your web server user must be able to write to storage and bootstrap/cache.

## Step 6, web server configuration

**The web server's document root MUST point to the public directory, NOT the project root.**

If you have a redirect from / to /public, remove it and configure the document root correctly instead.

### For DirectAdmin / shared hosting

**Option 1: Change document root (recommended)**

1. In DirectAdmin, go to Domain Setup or Domain Management.
2. Find your domain.
3. Look for Document Root or Public HTML Path setting.
4. Change it from:
   ```
   /home/username/domains/yourdomain.com/public_html
   ```
   To:
   ```
   /home/username/domains/yourdomain.com/public_html/public
   ```
5. Remove any redirect rule from / to /public (it's no longer needed).

**Option 2: Move files (if you can't change document root)**

```bash
# Move everything from public/ to public_html/
mv public/* public_html/
mv public/.htaccess public_html/

# Update paths in index.php
# Change: require __DIR__.'/../vendor/autoload.php';
# To: require __DIR__.'/vendor/autoload.php';
# (adjust paths as needed)
```

**Option 3: Use .htaccess rewrite (common solution for shared hosting)**

If you can't change the document root (common on shared hosting), create/update `.htaccess` in your `public_html/` root:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Route everything through /public/ except if already in /public/
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ /public/$1 [L]
</IfModule>
```

This is a perfectly valid solution for shared hosting where you can't change the document root. After adding this, remove any existing redirect rules from / to /public in your hosting control panel.

The `.htaccess` file in `public/` will handle URL rewriting from there.

### Apache (VPS/dedicated)

Point your virtual host to the public directory:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /path/to/WeatherNode/public

    <Directory /path/to/WeatherNode/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

The `.htaccess` file in `public/` will handle URL rewriting.

### Nginx

```nginx
server {
    listen 80;
    server_name example.com;
    root /path/to/WeatherNode/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Step 7, set up cron job

Laravel's scheduler needs to run every minute. The command must include cd to change to the project directory first.

```bash
* * * * * cd /path/to/WeatherNode && php artisan schedule:run >> /dev/null 2>&1
```

### For DirectAdmin

DirectAdmin's cron interface can be picky about command format. Try these options in order:

**Option 1: Simple command (recommended)**

If DirectAdmin has a "Working Directory" or "Path" field, use that and just enter:
```
php artisan schedule:run
```

Frequency: `* * * * *` (every minute)

Working Directory: `/home/username/domains/yourdomain.com/public_html`

**Option 2: Without redirection**

Some DirectAdmin versions don't like `&&` or redirection. Try using `;` instead of `&&` (semicolon runs the command regardless of whether the previous command succeeded):
```
cd /home/username/domains/yourdomain.com/public_html; php artisan schedule:run
```

**Option 3: Absolute PHP path**

Use full path to PHP and artisan:
```
/usr/local/bin/php /home/username/domains/yourdomain.com/public_html/artisan schedule:run
```

To find your PHP path, SSH in and run: `which php`

**Option 4: Wrapper script (most reliable)**

1. Create the script via SSH:
   ```bash
   nano /home/username/domains/yourdomain.com/public_html/run-scheduler.sh
   ```

2. Add this content:
   ```bash
   #!/bin/bash
   cd /home/username/domains/yourdomain.com/public_html
   /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```

3. Make it executable:
   ```bash
   chmod +x run-scheduler.sh
   ```

4. In DirectAdmin cron, use the full path to the script.

**Important:** Make sure the path points to your project root (where the `artisan` file is located), not just `public_html`. If your project is in a subdirectory, adjust the path accordingly.

### For cPanel

1. Go to Cron Jobs in cPanel.
2. Set all time fields to `*` (every minute).
3. Command:
   ```
   cd /home/username/path/to/WeatherNode && php artisan schedule:run >> /dev/null 2>&1
   ```

### Verify cron is working

```bash
# List scheduled tasks
php artisan schedule:list

# Test the scheduler manually (run from project directory)
php artisan schedule:run

# Check if cron is running (should show your cron job)
crontab -l
```

## Step 8, optimize for production

```bash
# Cache configuration
php artisan config:cache

# Cache routes (optional, skip if you encounter route errors)
php artisan route:cache

# Cache views
php artisan view:cache
```

Route caching is optional and can sometimes cause issues. If you encounter "Method not allowed" errors after caching, run `php artisan route:clear`. You can skip route caching entirely if you continue to have issues.

## Step 9, configure Ecowitt push security (if applicable)

If you use Ecowitt Local (push) via WS View:

1. Go to Admin > Settings > Live Data Source.
2. Select Ecowitt Local (push).
3. Enable Secure Push Mode.
4. Set/generate Endpoint Token and set Passkey.
5. In WS View, configure:
   - Server: your domain
   - Path: copy the exact path shown in admin (`/api/ecowitt/receive/<token>`)
   - Port: 443
   - Upload interval: 60s
   - Passkey: same value as admin

Validate the configuration:
```bash
php artisan system:readiness
```
When secure mode is enabled, the report includes `ecowitt_secure_receiver` and will fail if token or passkey is missing.

## Step 10, initial data and verification

Run an initial poll so the dashboard has cache data.

```bash
# Fetch live weather data
php artisan weather:fetch --save

# Poll all external APIs
php artisan weather:poll-external --force

# Generate daily summaries
php artisan weather:summarize
```

### Verify deployment

1. **Visit your site:** `https://yourdomain.com`
   - Should load the weather dashboard
   - Check browser console (F12) for any JavaScript errors

2. **Test API endpoint:** `https://yourdomain.com/api/weather/dashboard`
   - Should return JSON data
   - If you get 404, see "API endpoint returns 404" in Troubleshooting

3. **Check admin panel:** `https://yourdomain.com/admin`
   - Login with your configured admin credentials
   - Verify settings can be accessed

4. **Verify data is loading:**
   - Dashboard should show weather data (may take a few minutes for first poll)
   - Check that pollers are running: `php artisan schedule:list`

5. **Check logs:** `tail -f storage/logs/laravel.log`
   - Look for any errors or warnings

6. **Verify API access with the public key:**
   WeatherNode creates a public API key on first page load after migrations.
   You can view it at /admin/api-keys. For external clients: `php artisan api:key:create "My Client"`
   ```bash
   curl -sS -H X-API-Key:<public key> https://yourdomain.com/api/weather/dashboard
   ```

7. **Check readiness:**
   ```bash
   php artisan system:readiness --strict
   ```

## Post-deployment checklist

- [ ] `.env` has `APP_DEBUG=false`
- [ ] `.env` has correct `APP_URL` (https://)
- [ ] `.env` has `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`
- [ ] Database migrations completed
- [ ] Storage symlink created (`php artisan storage:link`)
- [ ] Permissions set correctly on storage and bootstrap/cache
- [ ] Cron job configured and running
- [ ] Frontend assets available (`public/build/manifest.json` exists; built on server or uploaded)
- [ ] Initial data polled (`php artisan weather:poll-external --force`)
- [ ] First admin account created with `php artisan admin:create`
- [ ] Ecowitt secure push configured (if using Ecowitt local push)
- [ ] API key auto-generated for browser use (external clients: `php artisan api:key:create "My Client"`)
- [ ] Web server configured correctly (document root OR .htaccess rewrite rule)
- [ ] `.htaccess` file exists in public directory (Apache)
- [ ] API endpoints working (`/api/weather/dashboard` returns data)
- [ ] SSL certificate configured (HTTPS)

## Updating the site

When updating to a new version:

```bash
# Pull latest code (if using Git)
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# If npm is available on server:
npm install
npm run build

# If npm is NOT available on server:
# fetch prebuilt assets from release (see SHARED_HOSTING_QUICKSTART.md)

# Run migrations (if any)
php artisan migrate --force

# Clear and rebuild caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

WeatherNode also supports an in-app updater via GitHub releases.
Configuration lives in config/updater.php.

```env
UPDATER_ENABLED=true
UPDATER_GITHUB_REPO=your-org/WeatherNode
```

### In-app updater command behavior

For browser-based ZIP deployments, the updater currently runs:

- `php artisan migrate --force`
- `php artisan config:clear`
- `php artisan cache:clear`
- `php artisan view:clear`
- `php artisan route:clear`

It does **not** run:

- `composer install`
- `composer update`
- `npm install`
- `npm run build`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

This is intentional for release ZIP updates: the release pipeline already builds assets and includes `vendor/` in `weathernode-deploy.zip`.

If you use `UPDATER_ALLOW_GIT=true` (Git-based update path), update to a version with dependency changes, or need fresh production caches, run these manually after update:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Avoid running `composer update` on production as part of normal updates. Resolve and lock dependency versions in development/CI, then deploy the tested lockfile.

## Moving to production checklist

Use this consolidated checklist when going live for the first time.

### 1. Environment variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
MAIL_FROM_ADDRESS=your-verified-email@yourdomain.com
MAIL_FROM_NAME="Your Station Name"
```

### 2. Security hardening

```bash
php artisan key:generate
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache
```

### 3. Database backup

Set up regular backups:
- SQLite: Copy `database/database.sqlite` regularly.
- MySQL: Use your hosting provider's backup tools or set up automated backups.

### 4. Performance optimization

```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 5. Final verification

- [ ] Dashboard loads correctly
- [ ] Admin panel accessible
- [ ] Weather data displaying
- [ ] Email test successful (Admin > Settings > Mail > Send Test Email)
- [ ] Cron job running
- [ ] API endpoints working
- [ ] SSL certificate installed (HTTPS)
- [ ] Error logging enabled and monitored

Run the built-in readiness validator before go-live:

```bash
# Readable report
php artisan system:readiness

# Strict mode for deploy scripts (fails when readiness is not PASS)
php artisan system:readiness --strict
```

## Troubleshooting

### 403 Forbidden

- Ensure `public/.htaccess` exists on the server. The file starts with a dot, so some FTP clients skip it by default.
- Verify `mod_rewrite` is enabled (Apache).
- Check that AllowOverride All is set for the public directory.
- If on shared hosting with .htaccess rewrite in public_html, ensure both `.htaccess` files exist (one in public_html root, one in public_html/public/).

### 500 Error

- Check `storage/logs/laravel.log`.
- Verify permissions on storage and bootstrap/cache.
- Ensure `.env` exists and has valid APP_KEY.

### Blank page

- Set `APP_DEBUG=true` temporarily to see errors.
- Verify `public/index.php` exists.
- Check PHP error logs.

### Assets not loading

- If npm is available on server, run `npm run build`.
- If npm is not available, fetch prebuilt assets from release (see shared-hosting quickstart).
- Verify `public/build/` directory exists and is readable by the web server.
- Check web server can serve files from public.

### "The GET method is not supported for route /. Supported methods: HEAD"

This is a route cache issue. The routes may have been cached incorrectly.

```bash
php artisan route:clear
```

If you're still in development or just deployed, you can skip route caching entirely. Route caching is only needed for production performance optimization.

### "Table 'database.settings' doesn't exist" during migration

The application tries to access the settings table during bootstrap (when loading console routes), but the table doesn't exist yet because migrations haven't completed.

The code handles this gracefully, but if you still see the error:

```bash
# Install without scripts, then set up manually
composer install --no-dev --optimize-autoloader --no-scripts
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan package:discover
```

What was fixed: The `routes/console.php` file wraps database calls in try-catch blocks, so migrations can run even if the settings table doesn't exist yet. The scheduler will use default values until the table is created.

### "npm: command not found"

**Option 1: Install Node.js on server (if you have root/sudo access)**

```bash
# For Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# For CentOS/RHEL
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo yum install -y nodejs

# Verify installation
node --version
npm --version
```

**Option 2: Fetch prebuilt release assets (no npm anywhere)**

If your hosting provider doesn't allow installing Node.js:

```bash
git fetch --tags
TAG="$(git tag --sort=-v:refname | head -n 1)"
git checkout "$TAG"
curl -L -o /tmp/weathernode-deploy.zip "https://github.com/centauri/WeatherNode/releases/download/${TAG}/weathernode-deploy.zip"
rm -rf /tmp/weathernode-release
mkdir -p /tmp/weathernode-release
unzip -o /tmp/weathernode-deploy.zip "public/build/*" -d /tmp/weathernode-release
rm -rf public/build
cp -R /tmp/weathernode-release/public/build public/build
```

The `public/build/` directory is what matters.

**Option 3: Build locally and upload**

If you prefer, you can still build on your own machine and upload `public/build/`.

**Option 4: Check for alternative Node.js**

Some shared hosting providers have Node.js available via cPanel Node.js Selector, custom paths, or version managers like nvm.

### "Script @php artisan package:discover returned error code 1"

Laravel's package discovery tries to connect to the database during composer install, but the database isn't set up yet.

Install in the correct order:

```bash
# Step 1: Install without running scripts
composer install --no-dev --optimize-autoloader --no-scripts

# Step 2: Configure .env
cp .env.example .env
php artisan key:generate
# Edit .env with your database credentials

# Step 3: Run database migrations
php artisan migrate --force

# Step 4: Run package discovery
php artisan package:discover

# Step 5: Build frontend assets (if npm is available)
npm install
npm run build
# If npm is not available, fetch prebuilt release assets (see Path A)
```

### API endpoint returns 404

1. **Clear route cache:**
   ```bash
   php artisan route:clear
   php artisan config:clear
   ```

2. **Verify routes are registered:**
   ```bash
   php artisan route:list | grep dashboard
   ```
   Should show: `GET|HEAD api/weather/dashboard`

3. **Check if API routes are enabled.** Verify in `bootstrap/app.php`:
   ```php
   ->withRouting(
       web: __DIR__.'/../routes/web.php',
       api: __DIR__.'/../routes/api.php',  // This should be present
   )
   ```

4. **Check web server configuration:**
   - Ensure `.htaccess` is in the public directory.
   - Verify `mod_rewrite` is enabled (Apache).
   - For Nginx, ensure proper rewrite rules.

5. **Test if other routes work:**
   - Try: `https://yourdomain.com/` (should show dashboard)
   - Try: `https://yourdomain.com/admin` (should show admin login)
   - If these work but `/api/*` doesn't, it's an API routing issue.

6. **Check Laravel logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   Then visit the API endpoint and see what error appears.

7. **Verify APP_URL in .env:**
   ```env
   APP_URL=https://yourdomain.com
   ```
   Then clear config: `php artisan config:clear`

8. **If using a subdirectory (not root):**
   If your Laravel app is in a subdirectory (e.g., `/public_html/app/`), API routes might need adjustment. Check your web server configuration points to the correct `public/` directory.

### Dashboard is empty but pollers are running

**Problem:** The scheduler is fetching data, but the dashboard shows empty or blank cards.

**Diagnosis steps:**

1. **Check if data is being cached:**
   ```bash
   php artisan tinker
   >>> Cache::get('astronomy_sun')
   >>> \App\Models\WeatherReading::mostRecent()
   ```

2. **Check cache driver in .env:**
   ```env
   CACHE_STORE=file
   ```
   Make sure the cache driver is set correctly. For most shared hosting, use `file`.

3. **Check cache permissions:**
   ```bash
   chmod -R 775 bootstrap/cache storage/framework/cache
   ```

4. **Manually populate cache:**
   ```bash
   php artisan weather:poll-external --force
   php artisan weather:fetch --save
   ```

5. **Check browser console** (F12) for JavaScript errors. Check the Network tab to see if `/api/weather/dashboard` is returning data.

6. **Test API endpoint directly:**
   Visit `https://yourdomain.com/api/weather/dashboard`
   - Should return JSON data.
   - If you see 404, see "API endpoint returns 404" above.
   - If you see errors, check `storage/logs/laravel.log`.

7. **Verify cache is working:**
   ```bash
   php artisan tinker
   >>> Cache::put('test', 'value', 60)
   >>> Cache::get('test')  // Should return 'value'
   ```

**Common solutions:**
- Cache driver issue: Set `CACHE_STORE=file` in .env and clear config: `php artisan config:clear`
- Permissions: Ensure `storage/framework/cache` and `bootstrap/cache` are writable
- Empty cache: Run `php artisan weather:poll-external --force` to populate
- Database issue: Verify `WeatherReading::mostRecent()` returns data in tinker
- JavaScript error: Check browser console for frontend errors

### Database connection errors

- Verify database file exists (SQLite) or connection works (MySQL).
- Check credentials in .env match your hosting provider's database.
- Test connection: `php artisan tinker` then `DB::connection()->getPdo();`
- Check database permissions: user needs CREATE, ALTER, INSERT, SELECT, UPDATE, DELETE.

### Scheduler and poller troubleshooting

Complete guide to diagnosing scheduler and poller issues.

#### 1. Check if scheduler is running

**Verify cron job exists:**
```bash
crontab -l
# Should show: * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

**Test scheduler manually:**
```bash
cd /path/to/WeatherNode
php artisan schedule:run
# Should show output like "Running scheduled command: weather:fetch --save"
```

**List all scheduled tasks:**
```bash
php artisan schedule:list
# Shows all scheduled commands with their intervals and next run time
```

**Check scheduler heartbeat (admin panel):**
- Visit `/admin/settings/scheduler`
- Should show "Last run" timestamp (updates every minute if cron is working)

#### 2. Check scheduler logs

All scheduler logs are in `storage/logs/`.

**Common poller log files:**
- `storage/logs/weather-fetch.log` - Live weather data (every minute)
- `storage/logs/poll-forecast.log` - Forecast (every 30 min)
- `storage/logs/poll-airquality.log` - Air quality (every 30 min)
- `storage/logs/poll-astronomy.log` - Sun/moon (every hour)
- `storage/logs/poll-aurora.log` - Aurora Kp-index (every 30 min)
- `storage/logs/poll-iss.log` - ISS passes (every hour)
- `storage/logs/poll-metar.log` - Aviation weather (every 30 min)
- `storage/logs/poll-alerts.log` - Weather alerts (every 15 min)
- `storage/logs/poll-earthquake.log` - Earthquakes (every 15 min)
- `storage/logs/weather-summary.log` - Daily summaries (midnight)
- `storage/logs/generate-nlg.log` - Forecast text generation
- `storage/logs/wu-history-sync.log` - Weather Underground sync
- `storage/logs/visitor-rollup.log` - Visitor analytics
- `storage/logs/geoip-update.log` - GeoIP database updates
- `storage/logs/cache-cleanup.log` - Cache maintenance

**Check for errors:**
```bash
# Search for errors across all logs
grep -i error storage/logs/*.log | tail -20

# Check specific poller failures
grep -i "failed\|error\|exception" storage/logs/poll-forecast.log

# Check log file timestamps (recent = pollers are running, old = may have stopped)
ls -lt storage/logs/*.log | head -10
```

#### 3. Check if pollers are actually fetching

**Test individual pollers manually:**
```bash
php artisan weather:poll-external --source=forecast --force
php artisan weather:poll-external --source=airquality --force
php artisan weather:poll-external --force
php artisan weather:fetch --save
```

- Should show "Fetching..." and "Cached..." messages.
- If you see "not due yet", the poller is working but waiting for its interval. Use `--force` to bypass.
- If you see errors, check the specific error message.

#### 4. Check cache data age

**Check when cache was last updated:**
```bash
php artisan tinker
```

Then in tinker:
```php
// Check forecast cache
$forecast = Cache::get('forecast_52.5_4.7');  // Replace with your lat/lng
$forecast ? 'Cache exists' : 'Cache MISSING';

// Check astronomy cache
Cache::get('astronomy_sun') ? 'Sun data cached' : 'Sun data MISSING';
Cache::get('astronomy_moon') ? 'Moon data cached' : 'Moon data MISSING';

// Check air quality cache
Cache::get('waqi_52.5_4.7') ? 'Air quality cached' : 'AQ MISSING';  // Replace lat/lng
```

**Expected cache ages:**
- Forecast: updates every 30 minutes (max age: 2 hours)
- Air Quality: updates every 30 minutes (max age: 2 hours)
- Astronomy (Sun/Moon): updates every hour (max age: 4 hours)
- Aurora: updates every 30 minutes (max age: 2 hours)
- ISS: updates every hour (max age: 4 hours)
- Weather Readings: updates every minute (max age: 5 minutes)

#### 5. Why a poller isn't fetching

**Check if poller is disabled in settings:**
```bash
php artisan tinker
>>> \App\Models\Setting::getValue('forecast.enabled', true)
>>> \App\Models\Setting::getValue('waqi.enabled', true)
>>> \App\Models\Setting::getValue('metar.enabled', false)
```

**Check poller interval tracking:**
```bash
# Pollers use smart interval tracking, they won't run if recently polled
php artisan weather:poll-external --source=forecast
# If you see "not due yet", the poller is working but waiting for its interval

# Force poll immediately (bypasses interval check)
php artisan weather:poll-external --source=forecast --force
```

**Check for API key issues:**
```bash
php artisan tinker
>>> \App\Models\Setting::getValue('waqi.api_key')
>>> \App\Models\Setting::getValue('metar.api_key')
```

**Check for rate limiting or network issues:**
- Some APIs (Yr.no, WAQI) have rate limits.
- Check logs for "rate limit" or "429" errors.
- Check for connection/SSL/timeout errors:
  ```bash
  grep -i "connection\|ssl\|timeout\|curl" storage/logs/laravel.log | tail -20
  ```

#### 6. Health check

**Run the built-in health check:**
```bash
php artisan system:readiness
```

#### 7. Common issues and solutions

**Cron job not running:**
- Verify cron syntax, check cron service is running.
- Test: `php artisan schedule:run` manually should work.

**Poller runs but no data in cache:**
- Check cache permissions, verify cache driver in `.env`.
- Test: `php artisan tinker` then `Cache::put('test', 'value', 60); Cache::get('test');`

**Poller shows "not due yet":**
- This is normal. Pollers respect intervals. Use `--force` to bypass.

**API errors in logs:**
- Check API keys, verify network connectivity, check rate limits.
- Run poller manually with `--force` to see full error output.

**Stale data on dashboard:**
- Check cache age, verify pollers are running, check logs for errors.
- Force poll: `php artisan weather:poll-external --force`

#### 8. Monitoring commands

```bash
# Watch scheduler run every minute
watch -n 60 'php artisan schedule:run'

# Monitor log files in real-time
tail -f storage/logs/weather-fetch.log storage/logs/poll-forecast.log

# Check scheduler heartbeat (stored in cache)
php artisan tinker
>>> Cache::get('scheduler:last_run')
# Should show recent timestamp if cron is working
```

## Security notes

1. Never commit `.env` - contains sensitive keys.
2. Set `APP_DEBUG=false` in production.
3. Use HTTPS - configure SSL certificate.
4. Bootstrap admin access with `php artisan admin:create`; rotate admin credentials regularly.
5. Restrict file permissions - only web server needs write access.
6. Keep dependencies updated - run `composer update` and `npm update` regularly.

## Optional: GeoIP setup

For visitor analytics, place GeoLite2 database at:
```
storage/app/private/geoip/GeoLite2-Country.mmdb
```

Update weekly:
```bash
php artisan geoip:update
```

Add to cron or use the built-in scheduler (updates automatically if MAXMIND_LICENSE_KEY is set in .env).
