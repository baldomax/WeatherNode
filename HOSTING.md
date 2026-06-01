# Hosting WeatherNode

> **Start here.** This is the front door for every supported way to host WeatherNode.
> It helps you pick the right setup, links to the step-by-step guide for each, and is
> explicit about the consequences of choosing something other than the recommended default.

For step-by-step install commands, see [DEPLOYMENT.md](DEPLOYMENT.md).
For a no-npm shared-hosting flow, see [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md).
For containers, see [DOCKER.md](DOCKER.md).

---

## Table of contents

1. [Quick decision guide](#quick-decision-guide)
2. [The one concept that matters: the web root](#the-one-concept-that-matters-the-web-root)
3. [The two layouts at a glance](#the-two-layouts-at-a-glance)
4. [Layout A — Auto-update-ready (recommended default)](#layout-a--auto-update-ready-recommended-default)
5. [Layout B — Static install](#layout-b--static-install)
6. [Which method for my environment?](#which-method-for-my-environment)
   - [Dedicated server / VPS](#dedicated-server--vps)
   - [Shared hosting — with document-root control](#shared-hosting--with-document-root-control)
   - [Shared hosting — no document-root control](#shared-hosting--no-document-root-control)
   - [Docker](#docker)
7. [The one-click updater: requirements & env vars](#the-one-click-updater-requirements--env-vars)
8. [Updating: which method for which layout](#updating-which-method-for-which-layout)
9. [Consequences of deviating from the default](#consequences-of-deviating-from-the-default)
10. [Where to go next](#where-to-go-next)

---

## Quick decision guide

Find the row that matches your situation. The **Layout** column links to the details.

| Your situation | Recommended | One-click updater? | How you update |
| --- | --- | --- | --- |
| VPS / dedicated server (root or full SSH) | [Layout A](#layout-a--auto-update-ready-recommended-default) | ✅ Yes | One click in admin, or Git |
| Shared hosting where you **can** set the document root | [Layout A](#layout-a--auto-update-ready-recommended-default) | ✅ Yes | One click in admin |
| Shared hosting where you **cannot** change the document root | [Layout B](#layout-b--static-install) | ❌ No | Git / manual / file sync |
| You run containers | [Docker](#docker) | ❌ No (pull a new image) | `docker pull` + recreate |
| Not sure / "just make it work simply" | [Layout B](#layout-b--static-install) | ❌ No | Git / manual / file sync |

**Recommended default:** [Layout A](#layout-a--auto-update-ready-recommended-default) whenever you can control the web root, because it unlocks safe one-click updates with automatic backup, health check, and atomic rollback. If you can't control the web root, Layout B is perfectly fine — you just update by hand.

---

## The one concept that matters: the web root

WeatherNode is a Laravel app. **Only the `public/` folder may be web-accessible.** Everything else — `app/`, `vendor/`, `.env`, `storage/` — must sit *outside* what the web server serves. If the web server serves the project root instead of `public/`, your source code and secrets (`.env`) become downloadable. That is the single most important rule, and it is what separates the two layouts below.

The two layouts are just two different answers to *"where does `public/` live, and is it a fixed folder or a swappable symlink?"*

---

## The two layouts at a glance

| | **Layout A — Auto-update-ready** | **Layout B — Static** |
| --- | --- | --- |
| Directory model | `releases/<version>/` + a `current` symlink + a shared `.env`/`storage` | One fixed app directory |
| Web root points to | `…/current/public` (follows the symlink) | `…/app/public` (fixed) |
| One-click browser updater | ✅ Works | ❌ Does **not** affect the live site |
| Atomic rollback from the UI | ✅ Yes | ❌ No (roll back manually) |
| Update methods | One-click, or Git | Git / manual upload / file sync (e.g. FreeFileSync) / Docker |
| Needs control of the web root | Yes | No |
| Best for | VPS, or shared hosts that let you set the docroot | Shared hosts with a fixed `public_html` |
| Setup effort | Medium (one-time) | Low |

> The browser updater is **Capistrano-style**: it extracts each release into `releases/<version>/`, then flips the `current` symlink. That only changes what visitors see if the web root *follows* `current`. With a static layout the symlink is never served, so updates extract and migrate the database but the served code never moves — the admin page will still show the old version. This is the most common "the update said OK but nothing changed" confusion; the cause is always a static layout.

---

## Layout A — Auto-update-ready (recommended default)

```
~/weathernode/                 ← UPDATER_DEPLOY_ROOT points here
├── releases/
│   ├── v2026.05.6/
│   └── v2026.05.7/            ← each browser update is extracted here
├── current -> releases/v2026.05.7   ← atomic symlink, flipped on each update
└── shared/                    ← survives across releases
    ├── .env                   ← symlinked into every release
    ├── storage/               ← logs, sessions, cache, uploads
    ├── database/              ← SQLite file (if used)
    └── backups/               ← pre-update backups the updater creates

# Web server document root  ->  ~/weathernode/current/public
```

**Why this is the default:** flipping `current` is atomic, so a deploy is either fully old or fully new — never half-applied. The in-app updater can take a backup, enter maintenance mode, run migrations, health-check the new release, and **roll back instantly** (code *and* database) if the health check fails. None of that is possible when the served folder is fixed.

**You need:** the ability to set the web server's document root to `…/current/public` (a VPS, or a shared host whose panel lets you set the docroot).

**Set up:** follow [DEPLOYMENT.md](DEPLOYMENT.md), then point the docroot at `current/public` and set the [updater env vars](#the-one-click-updater-requirements--env-vars).

---

## Layout B — Static install

```
~/domains/example.com/public_html/   ← the whole app lives here
├── app/  bootstrap/  vendor/  resources/  routes/  artisan  .env
└── public/                ← the only web-served folder
        └── index.php
# Web server document root  ->  …/public_html/public
#   (or …/public_html with an .htaccess rewrite into public/)
```

This is the classic shared-hosting layout and what [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md) sets up. It is secure and fully supported.

**What you give up:** the one-click browser updater. It may still appear in the admin panel, but because the live site is served from a fixed folder (not `current/`), clicking "update" extracts a release and runs migrations **but never changes the served code**. On Layout B you update with one of the manual methods below.

**Use it when:** your host fixes the document root to `public_html` and won't let you change it.

---

## Which method for my environment?

### Dedicated server / VPS

Use **Layout A**. Point your web server at `current/public`:

```nginx
# Nginx
server {
    server_name example.com;
    root /home/you/weathernode/current/public;   # follows the symlink
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```apache
# Apache
<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /home/you/weathernode/current/public
    <Directory /home/you/weathernode/current/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

> Tip: set `fastcgi_param SCRIPT_FILENAME $realpath_root$…` (Nginx) so PHP resolves the symlink target fresh. After the updater flips `current`, the new release is served immediately — the in-app updater also clears PHP's opcache/realpath cache for you.

One-click updates work out of the box once `UPDATER_ENABLED=true`.

### Shared hosting — with document-root control

Many panels (DirectAdmin, Plesk, some cPanel setups) let you set a domain's document root. If yours does, use **Layout A**: place the app under e.g. `~/weathernode/`, and set the domain's document root to `~/weathernode/current/public`. One-click updates then work exactly like on a VPS.

### Shared hosting — no document-root control

If the panel forces the docroot to `public_html`, use **Layout B**. Two equivalent options:

- **Set the docroot to `public_html/public`** if the panel allows even that one level. Cleanest.
- **`.htaccess` rewrite** in `public_html/` (when you can't change the docroot at all):
  ```apache
  <IfModule mod_rewrite.c>
      RewriteEngine On
      RewriteCond %{REQUEST_URI} !^/public/
      RewriteRule ^(.*)$ /public/$1 [L]
  </IfModule>
  ```
  This routes every request through `public/`, which keeps `app/`, `.env`, and `vendor/` unreachable. **Verify** it's working:
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" https://yourdomain.com/.env   # must be 403 or 404
  ```
  If that returns `200`, your secrets are exposed — fix the rewrite before going live.

Update via Git or file sync (see [Updating](#updating-which-method-for-which-layout)). Full walkthrough: [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md).

### Docker

Use the published image; the container already serves `public/` correctly. Updates are done by **pulling a new image tag and recreating the container** — the in-app updater does not apply (the container filesystem is immutable; persistent data lives in mounted volumes). See [DOCKER.md](DOCKER.md).

For containerized installs with custom host ports, set `APP_URL` with full scheme and port (example: `http://192.168.1.15:8089`) so auth redirects stay on the container URL.

---

## The one-click updater: requirements & env vars

**Requirement:** the web root must follow `current/public` ([Layout A](#layout-a--auto-update-ready-recommended-default)). On any other layout the updater UI is harmless but won't change the served site.

**What a one-click update does, in order:** create a backup (`.env` + database + storage) → enter maintenance mode → download & verify the release ZIP (SHA-256) → extract to `releases/<version>` → link shared `.env`/`storage` → run `php artisan migrate --force` → flip the `current` symlink → clear PHP opcache → health-check the new release → **roll back (code + database) if the health check fails** → leave maintenance mode. Old releases and backups are pruned to a configurable count, and you can delete them manually from **Admin → Settings → Updates**.

**Environment variables** (see `config/updater.php` for all of them):

```env
# Master switch for the in-app updater
UPDATER_ENABLED=true
UPDATER_GITHUB_REPO=centauri/WeatherNode

# Layout A paths. Defaults assume the app's own base_path() is the deploy root,
# which is correct for a VPS install where the docroot is <deploy_root>/current/public.
UPDATER_DEPLOY_ROOT=/home/you/weathernode   # dir that holds releases/, current, shared/
UPDATER_RELEASES_PATH=releases              # subdir for extracted releases
UPDATER_SHARED_PATH=shared                  # subdir for .env, storage, database, backups
UPDATER_CURRENT_SYMLINK=current             # the symlink the web root follows
UPDATER_KEEP_RELEASES=5                      # how many old releases to keep
UPDATER_BACKUP_KEEP=5                        # how many pre-update backups to keep

# Safety (recommended: leave on)
UPDATER_HEALTH_CHECK=true                    # verify the new release before committing
UPDATER_REQUIRE_CHECKSUM=true                # refuse releases without a trusted SHA-256
UPDATER_BACKUP_ENABLED=true
```

> On **Layout B**, leave `UPDATER_ENABLED=false` (or simply don't use the Updates page) and update with a manual method — otherwise you'll see "update succeeded" while the live site stays unchanged.

The exact artisan commands the updater runs (and the ones it deliberately does **not**, such as `composer install`/`npm run build`, because the release ZIP already bundles `vendor/` and built assets) are documented under "In-app updater command behavior" in [DEPLOYMENT.md](DEPLOYMENT.md).

---

## Updating: which method for which layout

| Layout / method | One-click (admin) | Git pull | Manual upload / file sync | Docker image |
| --- | --- | --- | --- | --- |
| **A — auto-update-ready** | ✅ primary | ✅ works | ⚠️ possible but bypasses atomic swap | — |
| **B — static** | ❌ no effect on live site | ✅ recommended | ✅ recommended (e.g. FreeFileSync) | — |
| **Docker** | ❌ | — | — | ✅ pull new tag, recreate |

For Git/manual updates on either layout, the post-update commands (`migrate --force`, cache rebuild, asset refresh when there's no server npm) are in the "Updating the site" section of [DEPLOYMENT.md](DEPLOYMENT.md).

> **File sync note (Layout B):** if you deploy by syncing files (FTP/FreeFileSync), use an **update/mirror-additive** mode that pushes changed files but **never deletes** server-only paths: `.env`, `storage/`, and any `shared/`, `releases/`, `current` left over from a previous updater experiment.

---

## Consequences of deviating from the default

Choosing something other than the recommended default is fine — as long as you do it knowingly. Here's exactly what each deviation costs you:

- **Static layout (B) instead of A** → No one-click updates and no UI rollback. The Updates page may say "success" while the served site is unchanged. You must update manually (Git / sync / Docker). *Mitigation:* set `UPDATER_ENABLED=false` so the misleading button is hidden.
- **Web root = project root instead of `public/`** → `.env`, source, and `vendor/` become downloadable. **Never do this.** Always serve `public/` only; verify with the `curl …/.env` check above.
- **App inside `public_html` with a broken/missing `.htaccess` rewrite** → same exposure risk as above. The rewrite (or a docroot set to `public_html/public`) is what protects you; test it.
- **Layout A without a real `shared/` directory** → logs, sessions, cache, uploads, and your `.env` won't persist across releases (each deploy starts blank, and you can lose login sessions). The updater seeds the `shared/storage` skeleton automatically, but `.env` and your database must live in `shared/`.
- **Disabling the health check (`UPDATER_HEALTH_CHECK=false`)** → a broken release can go live without auto-rollback. Only disable it temporarily to get past a one-off transition, then turn it back on.
- **Disabling checksum verification (`UPDATER_REQUIRE_CHECKSUM=false`)** → you'd deploy release ZIPs without verifying they're the trusted, untampered build. Leave it on.
- **Running `composer update` on production as part of an update** → pulls unreviewed dependency versions. Resolve/lock dependencies in development/CI and deploy the tested `composer.lock` instead.

---

## Where to go next

- **Full step-by-step install** (PHP, database, cron, storage, web server, production hardening): [DEPLOYMENT.md](DEPLOYMENT.md)
- **No server npm? Shared-hosting quickstart:** [SHARED_HOSTING_QUICKSTART.md](SHARED_HOSTING_QUICKSTART.md)
- **Containers:** [DOCKER.md](DOCKER.md)
- **Using the Updates page day-to-day:** [ADMIN_GUIDE.md](ADMIN_GUIDE.md)
- **Updater internals (what it runs / doesn't):** "In-app updater command behavior" in [DEPLOYMENT.md](DEPLOYMENT.md)
