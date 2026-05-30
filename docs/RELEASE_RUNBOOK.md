# Release Runbook

Use this as the quick reference for day-to-day commits, CI checks, releases, and Docker publishing.

## 1) Normal development push

Use this flow for regular updates to `main`.

```bash
# from repository root
git status
git add <files>
git commit -m "Your message"
git push
```

What happens automatically on push to `main`:

- Security Audit workflow runs.
- Docker image is built and pushed to GHCR.
- Docker tags:
  - `latest`
  - `main-<sha>`

## 2) Create an official release (ZIP + year-based Docker tag)

Use this when you want a downloadable release artifact and a versioned container image.

```bash
# from repository root
git tag vYYYY.MM.patch
git push origin vYYYY.MM.patch
```

What happens automatically on tag `v*`:

- Release workflow builds and tests.
- GitHub Release is created.
- Release assets are uploaded:
  - `weathernode-deploy.zip`
  - `weathernode-deploy.zip.sha256`
- Docker image is pushed with tag:
  - `vYYYY.MM.patch`

## 3) Verify published artifacts

Release ZIP:

- GitHub -> Releases -> latest tag
- Confirm both ZIP and `.sha256` files exist

Docker:

```bash
# replace <image> with your GHCR image path
docker pull <image>:latest
docker pull <image>:vYYYY.MM.patch
```

## 4) Versioning rule (important)

Use the year-based scheme consistently:

- Git tags (`v...`)
- `VERSION` file
- updater comparisons

Required format:

- `vYYYY.MM.patch` for releases (example: `v2026.03.0`)
- optional `-dev` suffix for development builds (example: `v2026.03.0-dev`)

Do not mix year-based and semver tags in the same update channel.

## 5) If CI fails

Open the failed workflow and copy the first concrete error block, then fix only that root cause.

Common examples:

- `MissingAppKeyException` during tests -> ensure test APP_KEY is defined.
- `manifest.json not found` -> build Vite assets before tests.
- Composer lock incompatibility -> ensure CI/Docker PHP version matches lockfile requirements.

