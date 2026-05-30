# WeatherNode

WeatherNode is a self hosted weather dashboard for your personal weather station.
It combines live station readings with external data like forecasts, alerts, radar, air quality, astronomy, and more.

I built WeatherNode for my own station first.
I wanted a dashboard that loads fast, stays usable during API outages, and lets me choose where the data comes from.
Once it worked well enough for daily use, I decided to share it so you can run it too.

If you are setting up a server, start with [DEPLOYMENT.md](DEPLOYMENT.md).

## Support

[![Buy me a coffee](https://img.buymeacoffee.com/button-api/?text=Buy%20me%20a%20coffee&emoji=&slug=centauriprime&button_colour=FFDD00&font_colour=000000&font_family=Cookie&outline_colour=000000&coffee_colour=ffffff)](https://www.buymeacoffee.com/centauriprime)

## Documentation

- Admin guide, [ADMIN_GUIDE.md](ADMIN_GUIDE.md). Use this if you run the site.
- User guide, [USER_GUIDE.md](USER_GUIDE.md). Use this if you visit the site.
- Deployment guide, [DEPLOYMENT.md](DEPLOYMENT.md)
- Shared hosting quickstart (no server npm), [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md)
- Development guide, [DEVELOPMENT.md](DEVELOPMENT.md)
- Versioning guide, [VERSIONING_GUIDE.md](VERSIONING_GUIDE.md)
- Release runbook (commit/build/release flow), [docs/RELEASE_RUNBOOK.md](docs/RELEASE_RUNBOOK.md)
- GitHub (source control and releases), [GITHUB.md](GITHUB.md)
- Changelog, [CHANGELOG.md](CHANGELOG.md)
- Telemetry aggregator notes, [docs/AGGREGATOR_SERVICE.md](docs/AGGREGATOR_SERVICE.md)
- License, [LICENSE.txt](LICENSE.txt)
- Trademarks, [TRADEMARKS.md](TRADEMARKS.md)

## Where the details live

This README stays focused on what WeatherNode is and what you get.
Detailed server setup lives in [DEPLOYMENT.md](DEPLOYMENT.md).
Detailed admin operations live in [ADMIN_GUIDE.md](ADMIN_GUIDE.md).

## Features

🌡️ Live station data

- Live station readings from Ecowitt, WeatherFlow, WeatherLink, Ambient Weather, Wunderground, and local file sources.
- Per card timestamps so you see when each data source last updated.
- Sensor health detection and OFFLINE badges when a source goes stale.
- Optional alert notifications when fetching or saving fails.

🌤️ External data

- Forecast sources include Yr.no, OpenWeatherMap, Weather Underground, Environment Canada, and Wxsim.
- Weather alerts support multiple providers by region.
- Air quality from WAQI and Sensor.Community.
- Aurora, ISS passes, astronomy calculations, earthquakes, and METAR.

🗺️ Pages

- Radar and satellite pages.
- Forecast, air quality, astronomy, earthquakes, lightning, and pressure map pages.
- History and statistics pages.
- Community stations map.

🧰 Admin and operations

- Admin panel for data sources, widgets, appearance, effects, mail, and updates.
- In app updater with preview, backups, and rollback.
- Cache first public site. WeatherNode updates data in the background and serves public pages from cache.

🎨 Customization

- Two public themes, FX and Flat.
- Admin drag and drop widget ordering on the dashboard.
- Optional visual effects like rain, snow, wind, lightning, and fog.

🌐 Locale and units

- Language and unit defaults set by the site admin.
- Optional browser based locale and region detection, with fallback to the site defaults.

🔐 API and security

- JSON API is protected by API keys. You manage keys at /admin/api-keys.
- Optional secure push mode for Ecowitt receivers.

📡 Community telemetry

- Optional station telemetry upload to a community aggregator.
- Community stations map page, if you enable telemetry.

🤖 Optional AI

- Built in forecast narrator.
- Optional AI rephrasing for forecast text, off by default.

## Requirements

- PHP 8.2 or newer.
- SQLite or MySQL.
- Composer.
- Node.js and npm for building frontend assets.

## Quick start for local development

```bash
git clone <your GitHub clone URL>
cd WeatherNode

composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate
php artisan db:seed
php artisan admin:create

npm run build
php artisan serve
```

Open http://localhost:8000 and sign in at /admin.

Local development helpers

```bash
composer run setup
composer run dev
composer test
```

## Admin bootstrap

`php artisan db:seed` seeds system settings.
Create your first admin account with:

```bash
php artisan admin:create
```

For automation, use non-interactive flags:

```bash
php artisan admin:create --email=admin@example.com --password="strong-password" --name="Administrator"
```

After login, users can change their own password from `/profile`.
If you lose admin access, use `php artisan admin:fix-access <email> --password="new-strong-password"`.

## Deployment

[DEPLOYMENT.md](DEPLOYMENT.md) covers production setup, cron, storage permissions, and web server configuration.
For in-app updater behavior (what it runs and what it does not), see the "In-app updater command behavior" section in [DEPLOYMENT.md](DEPLOYMENT.md).
If you do not have Node.js/npm available, use [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md) for a no-npm deployment flow.

## Docker container

WeatherNode is also available as a Docker image (published on GitHub Container Registry):

```bash
docker pull ghcr.io/centauri/weathernode:latest
```

Quick run example:

```bash
docker run -d --name weathernode \
  -p 8080:80 \
  --env-file .env \
  -v weathernode_storage:/var/www/html/storage \
  -v weathernode_cache:/var/www/html/bootstrap/cache \
  -v weathernode_db:/var/www/html/database \
  ghcr.io/centauri/weathernode:latest
```

Then initialize once:

```bash
docker exec weathernode php artisan migrate --force
docker exec weathernode php artisan db:seed
docker exec -it weathernode php artisan admin:create
docker exec weathernode php artisan storage:link
```

For full Docker usage (including the scheduler container), see [DOCKER.md](DOCKER.md).

## AI features

WeatherNode can generate short forecast text.
It uses a built in narrator.
You can enable optional AI rephrasing for that text, it is off by default.
Configuration lives in config/nlg.php and the related environment variables.
If you enable AI rephrasing, the app may send forecast text to the configured provider.

## Developer utilities

```bash
make clean
make release
```

`make release` expects a plain text/Markdown changelog bullet (raw HTML tags are rejected).
