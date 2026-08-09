# Changelog

All notable changes to this project will be documented in this file.

---

## [2026.08.2] - 2026-08-09

- Quick Stats bar tiles can now be toggled in Settings → Dashboard Widgets and reordered on the dashboard in edit mode (#13)
- Fix Docker upgrades silently skipping database migrations: migrations now run from a copy inside the image that no volume can cover; the data volume moves to `/var/lib/weathernode` (optional cleanup, existing compose files keep working — see DOCKER.md)
- Fix the in-app updater deleting a release's migrations on SQLite installs, so `migrate` ran against an empty folder and reported success
- Show a banner in the admin area when a new release is available (checked daily, can be turned off on the Updates page)
- Stop browser-caching the dashboard for admins so settings changes show up without a hard refresh
- Note: migrations skipped by either bug are applied on the first start after this update — take a backup before upgrading

## [2026.08.01] - 2026-08-07

- Fix webcam image refresh after the dashboard renders conditional widgets
- Refresh webcam snapshots in both image-only and image-with-stream modes
- Show a compact, mobile-friendly image update time and failure status
- Only show data saver controls for paused livestreams

## [2026.07.2] - 2026-07-31

- Localize weekly temperature chart day labels to the active site locale
- Return English i18n keys from WeatherReading accessors (compass, Beaufort, UV, PM2.5)
- Localize wind rose and history/day chart compass labels
- Neutralize hardcoded MeteoUitgeest branding defaults to WeatherNode
- Map realtime.txt average wind direction and daily max gust fields
- Unify missing jsLocale fallback to en-US

## [2026.07.1] - 2026-07-30

- Footer "Data since" year now uses `station.start_date` instead of a hardcoded 2020
- Statistics "Most sunshine hours" is populated from Cumulus/WD sunshine data (with radiation estimate fallback)
- Weather alerts widget shows up to 3 alerts with severity colors
- Fixed Docker multi-arch image `manifest unknown` pull errors

## [Unreleased]

### Added

- **In-page weather alert toasts** — non-intrusive slide-down notifications at the top-center of the dashboard for extreme conditions
  - Reuses existing `AlertAggregatorService` backend alerts (severity ≥ 3: lightning, UV, AQI, pollen, waves, fire, floods, frost)
  - Real-time frontend checks reusing existing FX condition booleans: heavy rain (≥ 10 mm/h), storm-force wind gusts (≥ 89 km/h), extreme heat (≥ 35 °C), extreme cold (≤ −10 °C), slippery roads (isSnowing)
  - Max 2 visible toasts; 12-second auto-dismiss; coloured left accent strip; manual dismiss button
  - All visual weather effects (rain drops, snow particles, wind, fog, lightning flash) remain completely independent and untouched
- **Frost warning** — `checkFrost()` added to `LocalWarningService`: scans the first 24 hourly forecast entries; severity 3 (orange) if min ≤ −2 °C, severity 2 (yellow) if ≤ 2 °C; `warning_type = 'frost'`
- **Pollen / Allergy Forecast** — new `/pollen` tab on `/air-quality`; dashboard widget links through
  - Data sources blended by priority: Ambee (paid, optional) → Google Pollen API (optional) → Open-Meteo Air Quality (free default)
  - Shows today's overall risk index, per-category risk badges (grass / tree / weed), grains/m³ counts, 5-day forecast bar chart, species breakdown table, colour-coded allergy advice cards
  - Polled hourly via `weather:poll-external --source=pollen`; admin settings at `/admin/settings/pollen`
- **Water page** — dedicated `/water` section replacing the old Sky & Sea tides tab, with four sub-routes served independently for fast loading:
  - **Tides** (`/water`) — Rijkswaterstaat Waterinfo API; tidal curve (12 h past + 48 h future), 3-day tide table, trend arrow, NAP reference; admin settings at `/admin/settings/tide`
  - **Waves** (`/water/waves`) — Open-Meteo Marine API; wave height/period/direction, wind wave vs. swell separation; admin settings at `/admin/settings/waves`
  - **Sea Temperature** (`/water/temp`) — sea surface temperature from Open-Meteo Marine with trend chart
  - **Rivers** (`/water/rivers`) — multi-provider river level data; Rijkswaterstaat (329+ stations via live catalog); provider registry pattern for future sources; admin settings at `/admin/settings/rivers`
- **Social Sharing Cards** — dynamic 1200×630 Open Graph PNG images served at `/og/*.png`
  - 9 card types: Home (live), Forecast, History, Statistics, Fire Weather, Air Quality, Astronomy, Aviation, Generic
  - Powered by `intervention/image` v3 with GD or Imagick driver (auto-detected, admin-selectable)
  - Station logo composited onto every card; dark branded design with per-page accent colours
  - PNGs are base64-encoded before caching so any cache driver (file, database, Redis) handles them safely
  - Admin settings page at `/admin/settings/og` with driver status badges and live preview links
  - All 14 public page views emit a dynamic `og:image` meta tag when OG is enabled
- **Share & Embed page** (`/share`) — public page accessible from the fat footer "Share & Embed" link
  - Large social share buttons for WhatsApp, X/Twitter, Facebook, Telegram, and copy-link
  - Per-page compact share buttons for Live, Forecast, Fire Weather, Statistics, Air Quality, Astronomy
  - Grid of OG card embed previews with `<img>` snippet + "Copy code" button when OG is enabled
- **Phenology / Season Tracker** — new section on `/statistics` (year-aware)
  - Day-type count grid (6 KNMI types): Frost, Ice, Spring, Summer, Tropical, Precipitation days
  - Seasonal milestones table: first/last occurrences vs. historical average date with ± days
  - GDD accumulation area chart (ApexCharts, base 10 °C, from Jan 1)
  - Powered by `PhenologyCalculator` service; cached per year, warmed daily at 00:10
- **Fire Weather page** (`/fire-weather`)
  - Angström Index with colour-coded danger badge (Low / Moderate / High / Extreme)
  - Consecutive dry days counter, 7-day and 30-day rolling rain totals
  - 90-day historical chart with ApexCharts, colour-coded markers, threshold annotation lines
  - Powered by `FireWeatherCalculator` service; cached until 00:10 next day
- **Statistics: climate comparison** — compare two date ranges side by side (JSON endpoint + tab UI)
- **Astronomy, Aviation, Forecast, Pressure pages** — added meta descriptions, scientific intro text, and localized content across 18 languages

### Changed

- `daily-cache-warm` scheduler (00:10 daily) extended to also rebuild OG image caches for fire weather and statistics year cards
- Home OG card cache TTL reduced from 30 minutes to 5 minutes (station updates every ~1 minute)
- Admin sidebar "Display" section now includes a "Social Sharing Cards" link

### Fixed

- Docker first-boot reliability improvements:
  - startup now normalizes writable permissions for mounted `storage/`, `bootstrap/cache`, and `database` paths to avoid readonly SQLite and log-file permission failures on stricter hosts
  - auth redirect URL generation now consistently honors configured `APP_URL` (including custom host ports such as `:8089`)
- Documentation updates for Docker/Unraid troubleshooting:
  - valid Laravel `APP_KEY` format (`base64:` + 32-byte key)
  - custom-port `APP_URL` examples and redirect verification
  - first-boot diagnostics workflow for isolating scheduler noise vs web request failures

- PHP 8.4 `ErrorException: Undefined array key` on `/admin/settings/og` when `og.*` settings had not yet been seeded — fixed by using `Collection::get()` instead of array access `[]`
- OG image endpoints returning a cascading `JsonException: Malformed UTF-8 characters` error (caused by binary PNG data appearing in Ignition's exception context) — fixed by wrapping all generation in `try/catch` with clean logging and base64-encoding cached values

---

## Notes

- See `temp/feature-roadmap.md` for upcoming features
- See `docs/` for legal, terms, and privacy documentation
