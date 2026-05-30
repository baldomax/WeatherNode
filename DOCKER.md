# Docker deployment

This document describes running WeatherNode in Docker and how to use it.

## Feasibility summary

**Yes, it is feasible** to run WeatherNode in Docker. The app is a standard Laravel 12 stack:

| Requirement | Docker approach |
|-------------|-----------------|
| PHP 8.2+ with extensions | Use official `php:8.2-fpm` and install `pdo`, `pdo_sqlite`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `fileinfo` (see DEPLOYMENT.md). |
| Composer | Run `composer install --no-dev` in the image build. |
| Node/npm (frontend build) | Multi-stage build: run `npm ci && npm run build` in a Node stage and copy `public/build` into the PHP image. No Node in the final image. |
| SQLite or MySQL | SQLite: named volume for `database/`. MySQL: use a `mysql` service in `docker-compose` and set `DB_*` in `.env`. |
| Web server (document root = `public/`) | Nginx runs in the same image via supervisord. |
| Laravel scheduler (cron) | A second container from the same image runs `php artisan schedule:run` every minute. |
| Writable storage | Named volumes for `storage/` and `bootstrap/cache`. |

## Quick start

1. **Copy env and set app key**
   ```bash
   cp .env.example .env
   # Edit .env (DB_CONNECTION=sqlite, APP_URL=http://localhost:8080, etc.)
   docker compose run --rm app php artisan key:generate
   ```

2. **Run migrations and create admin**
   ```bash
   docker compose run --rm app php artisan migrate --force
   docker compose run --rm app php artisan db:seed
   docker compose run --rm app php artisan admin:create --email=admin@example.com --password=changeme --name=Admin
   ```

3. **Create storage link**
   ```bash
   docker compose run --rm app php artisan storage:link
   ```

4. **Start stack**
   ```bash
   docker compose up -d
   ```
   Open http://localhost:8080 (or the port you set in docker-compose).

5. **Optional: initial data**
   ```bash
   docker compose exec app php artisan weather:fetch --save
   docker compose exec app php artisan weather:poll-external --force
   ```

## What’s included

- **Dockerfile**: Multi-stage build (Node for `npm run build`, then PHP 8.2-FPM + Nginx). Final image runs Nginx and PHP-FPM via supervisord.
- **docker-compose.yml**: `app` (web), `scheduler` (runs `schedule:run` every minute), and volumes for `storage/`, `bootstrap/cache`, and SQLite `database/`.
- **.dockerignore**: Keeps build context small (excludes `.git`, `node_modules`, `vendor`, `storage/logs`, etc.).

## Using MySQL instead of SQLite

In `docker-compose.yml`, uncomment the `mysql` service and the `depends_on` / `DB_HOST` for the app. In `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=weathernode
DB_USERNAME=weathernode
DB_PASSWORD=your_password
```

Run migrations (and optionally seed/create admin) as above.

## Notes

- **Laravel Sail**: The project has `laravel/sail` in `require-dev` for local development. This Docker setup is a self-contained production-style alternative.
- **Cron on the host**: Not required; the `scheduler` container replaces it.
- **Deploy script**: `deploy.sh` excludes `Dockerfile` and `docker-compose*.yml`, so they are not overwritten when deploying to a non-Docker server.
