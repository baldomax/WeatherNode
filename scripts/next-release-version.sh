#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

YEAR_MONTH="$(date -u +%Y.%m)"
TAG_PREFIX="v${YEAR_MONTH}."

LAST_PATCH="$(
	git tag --list "${TAG_PREFIX}*" \
	| sed -nE 's/^v[0-9]{4}\.[0-9]{2}\.([0-9]+)$/\1/p' \
	| sort -n \
	| tail -n 1 || true
)"

if [ -n "$LAST_PATCH" ]; then
	NEXT_PATCH=$((LAST_PATCH + 1))
else
	NEXT_PATCH=0
fi

printf 'v%s.%s\n' "$YEAR_MONTH" "$NEXT_PATCH"

