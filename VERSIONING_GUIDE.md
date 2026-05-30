# Versioning Guide

How versioning works in WeatherNode and how to manage dev vs production versions.

## How It Works

### Current Setup

- **VERSION file**: Single file at project root (`VERSION`)
- **Format**: Calendar versioning: `vYYYY.MM.patch` or `vYYYY.MM.patch-dev`
- **Example**: `v2026.03.0-dev` (dev), `v2026.03.0` (release), `v2026.03.1-dev` (next dev patch)
- **Policy**: Year-based tags only. Do not publish semver tags like `v1.2.3` in this repository.

### Version File Location

The app reads version from: `/VERSION` (project root)

```bash
# Current content
v2026.03.0-dev
```

## Versioning Strategy

### Recommended Workflow

**1. Development Version (Your Dev Machine)**
```
VERSION: v2026.03.0-dev
```
- Use `-dev` suffix for development
- Increment as you work: `v2026.03.1-dev`, `v2026.04.0-dev`, etc.

**2. Production Release (GitHub + Production Site)**
```
VERSION: v2026.03.0
```
- Remove `-dev` suffix when releasing
- Use calendar versioning: `v2026.03.0`, `v2026.03.1`, `v2026.04.0`, `v2027.01.0`

### Version Numbering Rules

- **YEAR** (`YYYY`): Main release year
- **MONTH** (`MM`): Monthly release bucket (`01` to `12`)
- **PATCH** (`patch`): Incremental fixes within the same month

**Examples:**
- `v2026.03.0` → `v2026.03.1` (bug fix)
- `v2026.03.5` → `v2026.04.0` (new month)
- `v2026.12.9` → `v2027.01.0` (new year rollover)

## GitHub Integration

### How GitHub Releases Work

1. **Create a GitHub Release** with tag matching your version:
   - Tag: `v2026.03.0` (must match VERSION file)
   - Release title: `v2026.03.0` or `Version 2026.03.0`
   - Upload: `weathernode-deploy.zip` (deployment package)

2. **The updater checks GitHub** and compares:
   - Current: `v2026.03.0` (from VERSION file on production)
   - Latest: `v2026.03.1` (from GitHub release tag)
   - If latest > current → Update available

### Release Notes Safety

- The admin Updates page renders release notes as sanitized Markdown.
- Raw HTML in GitHub release notes may be stripped for security.
- Keep release notes in normal Markdown (headings, lists, links).
- `make release` now rejects changelog bullet input that includes `<` or `>`.

### Version Comparison

The updater compares versions using PHP `version_compare` on normalized tags:
- `v2026.03.0` < `v2026.03.1` ✓
- `v2026.03.9` < `v2026.04.0` ✓
- `v2026.12.4` < `v2027.01.0` ✓
- `v2026.03.0-dev` < `v2026.03.0` ✓ (dev versions are always "less than" production)

⚠️ Keep all published tags in the same year-based format. Mixing semver tags (`v1.x.y`) with year-based tags (`vYYYY.MM.patch`) can produce misleading update results in the admin updater.

## Helper Commands

We've created Artisan commands to make version management easier:

```bash
# Show current version
php artisan version show

# Set version manually
php artisan version set v2026.03.0
php artisan version set v2026.03.0-dev

# Show next auto-generated release tag for current month
sh scripts/next-release-version.sh

# Bump version (auto-increment)
php artisan version bump patch    # v2026.03.0 → v2026.03.1
php artisan version bump month    # v2026.03.0 → v2026.04.0
php artisan version bump year     # v2026.12.0 → v2027.01.0

# Prepare for release (removes -dev, shows next steps)
php artisan version release

# Prepare release files/changelog with auto-suggested next version
make release
```

### GitHub Auto-Tag Release

- The `Release (build deploy ZIP)` workflow now supports **Run workflow** (`workflow_dispatch`).
- Manual trigger flow:
  1. Workflow computes next tag using `scripts/next-release-version.sh` (format `vYYYY.MM.patch`)
  2. Workflow creates and pushes the tag
  3. A new tag-triggered workflow run builds `weathernode-deploy.zip` and publishes the GitHub release

## Workflow Examples

### Example 1: First Production Release

**On Dev Machine:**
```bash
# 1. Update VERSION file (using helper)
php artisan version set v2026.03.0

# Or prepare from dev version
php artisan version release  # Removes -dev suffix

# 2. Commit and push
git add VERSION
git commit -m "Release v2026.03.0"
git tag v2026.03.0
git push origin main --tags

# 3. Create GitHub release
# - Tag: v2026.03.0
# - Upload weathernode-deploy.zip
```

**On Production:**
- After deployment, VERSION file will be `v2026.03.0`
- Updater will check GitHub and see you're on latest

### Example 2: Development Cycle

**On Dev Machine:**
```bash
# Working on new features
php artisan version set v2026.04.0-dev
# ... make changes ...
git commit -m "WIP: New features"

# When ready to release
php artisan version release  # Removes -dev, shows next steps
git add VERSION
git commit -m "Release v2026.04.0"
git tag v2026.04.0
git push origin main --tags
```

**On Production:**
- Production stays on `v2026.03.0`
- Updater shows: "Update available: v2026.04.0"
- Admin can update via browser or manual ZIP

### Example 3: Hotfix

**On Dev Machine:**
```bash
# Bug found in production v2026.03.0
php artisan version set v2026.03.1-dev
# ... fix bug ...
php artisan version release  # Prepares for release
git add VERSION
git commit -m "Hotfix v2026.03.1"
git tag v2026.03.1
git push origin main --tags
```

**On Production:**
- Production on `v2026.03.0`
- Updater shows: "Update available: v2026.03.1"
- Admin updates → Production becomes `v2026.03.1`

## Best Practices

### ✅ DO

1. **Keep dev version with `-dev` suffix** while developing
2. **Remove `-dev` when releasing** to production
3. **Match GitHub tag to VERSION file** exactly
4. **Increment version before release**, not after
5. **Commit VERSION file** to Git

### ❌ DON'T

1. **Don't use `-dev` in production** (updater won't work correctly)
2. **Don't skip planned release buckets** (keep month/patch progression understandable)
3. **Don't forget to update VERSION** before creating GitHub release
4. **Don't use different formats** (stick to `vYYYY.MM.patch`)

## Quick Reference

### Update Version on Dev
```bash
# Manual way
echo "v2026.03.0" > VERSION

# Or use the helper command (recommended)
php artisan version set v2026.03.0
php artisan version bump patch    # Increment patch: v2026.03.0 → v2026.03.1
php artisan version bump month    # Increment month: v2026.03.0 → v2026.04.0
php artisan version bump year     # Increment year: v2026.12.0 → v2027.01.0
```

### Check Current Version
```bash
# Quick check
php artisan version show

# Or manual
cat VERSION

# Or in PHP:
php artisan tinker
>>> \App\Services\VersionService::getAppVersion()
```

### Prepare for Release
```bash
# Automatically removes -dev suffix and shows next steps
php artisan version release
```

### Create GitHub Release
1. Update `VERSION` file
2. Commit and push
3. Create release on GitHub with matching tag
4. Upload deployment ZIP

### Version in Production
- Production reads from `/VERSION` file
- After update, VERSION file is updated automatically
- Updater compares production VERSION vs GitHub release tags

## FAQ

**Q: Do I need separate dev and prod VERSION files?**  
A: No. Use one VERSION file. Change it from `-dev` to production version when releasing.

**Q: What if I forget to update VERSION before release?**  
A: The updater will still work, but version numbers won't match. Always update VERSION first.

**Q: Can I use `-beta` or `-rc` tags?**  
A: Not with the helper command. The supported format is `vYYYY.MM.patch` with optional `-dev`.

**Q: How does the updater know which version is newer?**  
A: It uses `version_compare` on normalized tags. `v2026.03.0` < `v2026.03.1` < `v2026.04.0` < `v2027.01.0`.

**Q: What happens if production VERSION is `v2026.03.0-dev`?**  
A: The updater will still work, but it's not recommended. Production should use clean version numbers.
