#!/usr/bin/env bash
# Validate a TaskConnect release tree or zip produced by `tc release`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${1:-$ROOT/dist}"

fail() {
  echo "release validation failed: $*" >&2
  exit 1
}

require_file() {
  local path="$1"
  [[ -f "$path" ]] || fail "missing file: $path"
}

require_dir() {
  local path="$1"
  [[ -d "$path" ]] || fail "missing directory: $path"
}

APP=""
ZIP=""

if [[ -d "$TARGET/app" ]]; then
  APP="$TARGET/app"
elif [[ -f "$TARGET/artisan" ]]; then
  APP="$TARGET"
fi

if [[ -f "$TARGET/taskconnect-release.zip" ]]; then
  ZIP="$TARGET/taskconnect-release.zip"
elif [[ -f "$TARGET" && "$TARGET" == *.zip ]]; then
  ZIP="$TARGET"
fi

if [[ -z "$APP" && -z "$ZIP" ]]; then
  fail "expected dist/app, an unpacked app tree, or taskconnect-release.zip under $TARGET"
fi

if [[ -n "$ZIP" ]]; then
  require_file "$ZIP"
  if [[ -f "${ZIP}.sha256" ]]; then
    (
      cd "$(dirname "$ZIP")"
      sha256sum -c "$(basename "$ZIP").sha256"
    ) || fail "sha256 mismatch for $(basename "$ZIP")"
  else
    echo "warning: ${ZIP}.sha256 not found; skipping checksum"
  fi

  command -v unzip >/dev/null || fail "unzip is required to inspect the release zip"
  TMP="$(mktemp -d)"
  trap 'rm -rf "$TMP"' EXIT
  unzip -q "$ZIP" -d "$TMP"
  if [[ -d "$TMP/app" ]]; then
    APP="$TMP/app"
  else
    fail "zip does not contain top-level app/"
  fi
fi

require_file "$APP/artisan"
require_dir "$APP/vendor"
require_dir "$APP/public/build"
require_file "$APP/public/build/manifest.json"

if [[ -d "$APP/node_modules" || -d "$APP/frontend/node_modules" ]]; then
  fail "release must not include node_modules"
fi
if [[ -d "$APP/tests" ]]; then
  fail "release must not include tests/"
fi

echo "Release validation OK ($APP${ZIP:+; zip=$(basename "$ZIP")})"
