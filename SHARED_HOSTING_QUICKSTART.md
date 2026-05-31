# Shared Hosting Quickstart (No npm Required)

> **Picking a hosting setup first?** See [HOSTING.md](HOSTING.md) to compare options and
> trade-offs. This quickstart sets up a **static install (Layout B)** — solid and fully
> supported, but note the one-click in-app updater does **not** drive a static install;
> you update via Git or file sync (covered at the end of this page). If you can set your
> domain's document root, the auto-update-ready layout in [HOSTING.md](HOSTING.md) unlocks
> one-click updates.

Use this when your hosting provider does not allow Node.js/npm.

## What you need

- Hosting account with PHP 8.2+, Composer, and a database (SQLite or MySQL).
- Cron access.
- `curl` and `unzip` on the server (for fetching prebuilt assets from releases).

## 1) Clone and install on server (from Git)

Run this on the server:

```bash
git clone https://github.com/centauri/WeatherNode.git
cd WeatherNode
git fetch --tags
TAG="$(git tag --sort=-v:refname | head -n 1)"
git checkout "$TAG"
composer install --no-dev --optimize-autoloader --no-scripts
cp .env.example .env && php artisan key:generate
# edit .env with APP_URL + DB settings
php artisan migrate --force && php artisan package:discover && php artisan db:seed
php artisan admin:create
php artisan storage:link
```

## 2) Fetch prebuilt frontend assets (no npm)

Run this on the server:

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

Confirm assets exist:

```bash
ls public/build/manifest.json
```

## 3) Web root setup

- Preferred: set document root to the app `public/` directory.
- If your host cannot change document root, use the `.htaccess` rewrite method from `DEPLOYMENT.md`.

## 4) Cron setup (required)

Add this cron job (every minute):

```bash
* * * * * cd /path/to/WeatherNode && php artisan schedule:run >> /dev/null 2>&1
```

## 5) Quick verification

```bash
php artisan system:readiness
```

Check:
- `/admin` login works with the account from `php artisan admin:create`
- dashboard loads without missing CSS/JS
- weather data appears after first poll cycle

## Updating later (still no npm on server)

1. Update code on server:
   ```bash
   cd WeatherNode
   git fetch --tags
   TAG="$(git tag --sort=-v:refname | head -n 1)"
   git checkout "$TAG"
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
2. Refresh prebuilt assets from latest release:
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

For full troubleshooting and alternative server setups, use `DEPLOYMENT.md`.
