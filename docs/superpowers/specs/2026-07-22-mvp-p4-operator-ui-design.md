# MVP P4 Design — Operator UI polish + E2E smoke expansion

## Goal

Expose P3 API capabilities in the SPA and deepen Playwright smoke coverage.

## Scope

1. **Tenant outbound allowlist UI** on Settings (edit hosts, PATCH tenant).
2. **Success status ranges** in task wizard retry section + `RetryPolicy` types.
3. **Playwright** — login form smoke + forgot-password link; optional authenticated dashboard smoke when `E2E_EMAIL`/`E2E_PASSWORD` set.

## Non-goals

- Full 8-step wizard rewrite
- Visual redesign
- GitHub Actions
