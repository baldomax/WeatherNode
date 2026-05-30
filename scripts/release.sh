#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

CURRENT_VERSION="$(tr -d '\r\n' < VERSION 2>/dev/null || true)"
if [ -z "$CURRENT_VERSION" ]; then
	CURRENT_VERSION="$(sh "$ROOT_DIR/scripts/next-release-version.sh")-dev"
fi

printf 'Current version: %s\n' "$CURRENT_VERSION"
SUGGESTED_VERSION="$(sh "$ROOT_DIR/scripts/next-release-version.sh")"
printf 'Next release version [%s]: ' "$SUGGESTED_VERSION"
IFS= read -r VERSION_INPUT
VERSION_INPUT="$(printf '%s' "$VERSION_INPUT" | tr -d '[:space:]')"

if [ -z "$VERSION_INPUT" ]; then
	NEW_VERSION="$SUGGESTED_VERSION"
else
	case "$VERSION_INPUT" in
		v*) NEW_VERSION="$VERSION_INPUT" ;;
		*) NEW_VERSION="v$VERSION_INPUT" ;;
	esac
fi

if ! printf '%s' "$NEW_VERSION" | grep -Eq '^v[0-9]{4}\.(0[1-9]|1[0-2])\.[0-9]+(-dev)?$'; then
	echo "Error: invalid version format. Use vYYYY.MM.patch or vYYYY.MM.patch-dev." >&2
	exit 1
fi

printf 'Changelog note (one short bullet): '
IFS= read -r CHANGELOG_NOTE

if [ -z "$(printf '%s' "$CHANGELOG_NOTE" | tr -d '[:space:]')" ]; then
	echo "Error: changelog note is required." >&2
	exit 1
fi

if printf '%s' "$CHANGELOG_NOTE" | grep -Eq '[<>]'; then
	echo "Error: changelog note must not contain raw HTML tags (< or >)." >&2
	echo "Use plain text or Markdown formatting." >&2
	exit 1
fi

if [ -f artisan ] && [ -d vendor ]; then
	php artisan version set "$NEW_VERSION" >/dev/null
else
	printf '%s\n' "$NEW_VERSION" > VERSION
fi

if [ ! -f CHANGELOG.md ]; then
	cat > CHANGELOG.md <<'EOF'
# Changelog

All notable changes to this project will be documented in this file.
EOF
fi

DATE_STAMP="$(date +%Y-%m-%d)"
VERSION_TAG="${NEW_VERSION#v}"
SECTION_TMP="$(mktemp "${TMPDIR:-/tmp}/wn-changelog-section.XXXXXX")"
OUTPUT_TMP="$(mktemp "${TMPDIR:-/tmp}/wn-changelog-output.XXXXXX")"
trap 'rm -f "$SECTION_TMP" "$OUTPUT_TMP"' EXIT INT TERM

cat > "$SECTION_TMP" <<EOF
## [$VERSION_TAG] - $DATE_STAMP
- $CHANGELOG_NOTE

EOF

FIRST_SECTION_LINE="$(grep -n '^## \[' CHANGELOG.md | head -n 1 | cut -d: -f1 || true)"

if [ -n "$FIRST_SECTION_LINE" ]; then
	head -n "$((FIRST_SECTION_LINE - 1))" CHANGELOG.md > "$OUTPUT_TMP"
	cat "$SECTION_TMP" >> "$OUTPUT_TMP"
	tail -n +"$FIRST_SECTION_LINE" CHANGELOG.md >> "$OUTPUT_TMP"
else
	cat CHANGELOG.md > "$OUTPUT_TMP"
	printf '\n' >> "$OUTPUT_TMP"
	cat "$SECTION_TMP" >> "$OUTPUT_TMP"
fi

mv "$OUTPUT_TMP" CHANGELOG.md
trap - EXIT INT TERM
rm -f "$SECTION_TMP"

printf 'Updated VERSION to %s\n' "$NEW_VERSION"
printf 'Added changelog entry to CHANGELOG.md\n'
