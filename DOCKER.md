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

1. **Edit `docker-compose.yml`**
   - Update `APP_URL` for your host/domain.
   - Replace `APP_KEY` with a real key.
   - Optional: set `ADMIN_EMAIL` and `ADMIN_PASSWORD` for first-run admin creation.

   Generate a key with:
   ```bash
   php artisan key:generate --show
   ```

2. **Start stack**
   Preferred helper:
   ```bash
   make docker-up
   ```

   Direct compose command:
   ```bash
   docker compose up -d
   ```
   Open http://localhost:8080 (or the port you set in docker-compose).

   On startup, the `app` container now:
   - runs `php artisan migrate --force` (default on every start)
   - runs first-run bootstrap once (`storage:link`, `db:seed`, optional `admin:create` via env)

3. **Optional: initial weather data**
   ```bash
   docker compose exec app php artisan weather:fetch --save
   docker compose exec app php artisan weather:poll-external --force
   ```

If you changed `Dockerfile` or other build-time files, rebuild with:

```bash
make docker-rebuild
```

## What’s included

- **Dockerfile**: Multi-stage build (Node for `npm run build`, then PHP 8.2-FPM + Nginx). Final image runs Nginx and PHP-FPM via supervisord.
- **docker-compose.yml**: `app` (web), `scheduler` (runs `schedule:run` every minute), compose-managed environment values (no `.env` copy required), and volumes for `storage/`, `bootstrap/cache`, and SQLite `database/`.
- **docker/entrypoint.sh**: startup bootstrap for migrations and one-time first-run initialization.
- **.dockerignore**: Keeps build context small (excludes `.git`, `node_modules`, `vendor`, `storage/logs`, etc.).

## Using MySQL instead of SQLite

In `docker-compose.yml`, uncomment the `mysql` service and set these values in the shared environment block:

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
- **Bootstrap toggles**: set `DOCKER_AUTO_MIGRATE` or `DOCKER_AUTO_SEED` to `"false"` in `docker-compose.yml` to disable automatic startup actions.
- **Makefile helpers**:
  - `make docker-up` checks that the `APP_KEY` placeholder was replaced, then runs `docker compose up -d`.
  - `make docker-rebuild` does the same check, then runs `docker compose build --no-cache && docker compose up -d`.
