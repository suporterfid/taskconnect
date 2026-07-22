#!/usr/bin/env bash
# Validate a TaskConnect release tree or zip produced by `tc release`.
# Includes secret hygiene scan (R9): fails on .env, private keys, or credential-like literals.
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

# --- R9 secret hygiene -------------------------------------------------------

scan_secrets() {
  local tree="$1"
  local hit

  # Real env files (placeholders belong only in .env.example outside the release).
  hit="$(find "$tree" -type f \( -name '.env' -o -name '.env.*' \) ! -name '.env.example' 2>/dev/null | head -n 5 || true)"
  if [[ -n "$hit" ]]; then
    fail "release must not contain .env files:\n$hit"
  fi

  # Private key material.
  hit="$(find "$tree" -type f \( \
      -name '*.pem' -o -name '*.key' -o -name 'id_rsa' -o -name 'id_ed25519' -o -name '*.p12' -o -name '*.pfx' \
    \) 2>/dev/null | head -n 5 || true)"
  if [[ -n "$hit" ]]; then
    fail "release must not contain private key material:\n$hit"
  fi

  hit="$(grep -RIl --binary-files=without-match -E 'BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY' "$tree" 2>/dev/null | head -n 5 || true)"
  if [[ -n "$hit" ]]; then
    fail "release must not contain PEM private key blocks:\n$hit"
  fi

  # Obvious live credential assignments (not empty / null / placeholder tokens).
  # Allow APP_KEY=base64:… only if clearly placeholder-length; reject long random-looking secrets.
  hit="$(grep -RIn --binary-files=without-match -E \
    '(^|[^A-Z0-9_])(AWS_SECRET_ACCESS_KEY|AWS_ACCESS_KEY_ID|GITHUB_TOKEN|GH_TOKEN|NPM_TOKEN|STRIPE_(SECRET|SECRET_KEY)|OPENAI_API_KEY|DATABASE_URL)\s*=\s*[^[:space:]#]+' \
    "$tree" 2>/dev/null | grep -vE '=\s*($|null|changeme|ChangeMe|your-|YOUR_|example|placeholder|xxx)' | head -n 10 || true)"
  if [[ -n "$hit" ]]; then
    fail "release contains credential-like literals:\n$hit"
  fi

  # Hard-coded high-entropy bearer-ish tokens in PHP/JS sources (heuristic).
  hit="$(grep -RIn --binary-files=without-match -E \
    "(sk_live_|rk_live_|xox[baprs]-)[A-Za-z0-9_-]{8,}" \
    "$tree" 2>/dev/null | head -n 5 || true)"
  if [[ -n "$hit" ]]; then
    fail "release contains token-like literals:\n$hit"
  fi
}

scan_secrets "$APP"

echo "Release validation OK ($APP${ZIP:+; zip=$(basename "$ZIP")}; secret-scan=pass)"
