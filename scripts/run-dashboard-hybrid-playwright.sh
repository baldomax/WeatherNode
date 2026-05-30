#!/bin/sh

set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
TEST_SCRIPT="$ROOT_DIR/tests/E2E/dashboard_hybrid_playwright.py"

if [ ! -f "$TEST_SCRIPT" ]; then
  echo "Playwright test script not found: $TEST_SCRIPT" >&2
  exit 1
fi

pick_python() {
  if [ -n "${PLAYWRIGHT_PYTHON:-}" ] && [ -x "${PLAYWRIGHT_PYTHON}" ]; then
    echo "$PLAYWRIGHT_PYTHON"
    return 0
  fi

  if command -v python3 >/dev/null 2>&1; then
    if python3 -c "import playwright" >/dev/null 2>&1; then
      echo "python3"
      return 0
    fi
  fi

  if command -v playwright >/dev/null 2>&1; then
    play_bin="$(command -v playwright)"
    play_shebang="$(head -n 1 "$play_bin" | sed 's/^#!//')"
    if [ -n "$play_shebang" ] && [ -x "$play_shebang" ]; then
      if "$play_shebang" -c "import playwright" >/dev/null 2>&1; then
        echo "$play_shebang"
        return 0
      fi
    fi
  fi

  if command -v python >/dev/null 2>&1; then
    if python -c "import playwright" >/dev/null 2>&1; then
      echo "python"
      return 0
    fi
  fi

  return 1
}

PY_BIN="$(pick_python || true)"
if [ -z "$PY_BIN" ]; then
  cat >&2 <<'EOF'
No Python runtime with Playwright found.
Set PLAYWRIGHT_PYTHON=/path/to/python (with playwright installed), then run again.
EOF
  exit 1
fi

exec "$PY_BIN" "$TEST_SCRIPT" "$@"
