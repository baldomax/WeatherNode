# Contributing to WeatherNode

Thank you for considering contributing. This document explains how to get started, run the project, and submit changes.

## Documentation to read first

- **[README.md](README.md)** — What WeatherNode is and quick start.
- **[DEVELOPMENT.md](DEVELOPMENT.md)** — Local setup, tests, scheduler, and code layout.
- **[VERSIONING_GUIDE.md](VERSIONING_GUIDE.md)** — Version format and release workflow (only relevant if you help with releases).

## How to contribute

1. **Open an issue** — For bugs or feature ideas, open a GitHub issue so we can align before you invest time.
2. **Fork and clone** — Fork the repo and clone your fork.
3. **Set up locally** — Follow [DEVELOPMENT.md](DEVELOPMENT.md) (e.g. `composer run setup`, then `composer run dev`).
4. **Create a branch** — Use a short branch name, e.g. `fix/radar-cache` or `feature/pollen-widget`.
5. **Make changes and test** — Run `composer test` (or `php artisan test`) before pushing.
6. **Format** — Run `./vendor/bin/pint` for PHP style.
7. **Push and open a PR** — Push your branch and open a pull request against the default branch. Describe what changed and why.

## Code and quality

- **Tests** — New features or bug fixes should be covered by tests where practical. See existing tests in `tests/`.
- **Style** — PHP: Laravel Pint. JS/CSS: follow existing patterns.
- **No secrets** — Do not commit `.env`, API keys, or credentials. Use `.env.example` as a template.

## Release and versioning

If you are not a maintainer, you don’t need to create releases. Versioning and release steps are documented in [VERSIONING_GUIDE.md](VERSIONING_GUIDE.md) and [GITHUB.md](GITHUB.md).

## Questions

Open a GitHub discussion or issue if something is unclear.
