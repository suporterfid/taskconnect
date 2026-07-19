# API Keys CRUD Design

**Date:** 2026-07-18  
**Status:** Approved  
**Site:** https://hub.taskconnect.com.br/api-keys

## Problem

The API Keys page is read-only. The API already supports create and revoke; there is no update endpoint, and the list hides revoked keys. Frontend types mismatch the API (`prefix` vs `key_prefix`).

## Decisions

- **UI approach:** Inline CRUD on a single `/api-keys` page (list + create/edit form panel), matching Environments.
- **Editable after create:** Name, expiry, and permissions. Environment is set only on create and stays immutable.
- **Permissions UX:** Curated checklist — Full access (`*`) plus known scopes. Selecting Full access stores `['*']` only.
- **Revoked keys:** Remain on the list with a **Revoked** badge; view-only; no restore/unrevoke.
- **Secret:** Plaintext shown once after create (copy + dismiss); never returned on list/update.
- **No secret regeneration** — revoke and create a new key instead.

## Backend

Routes under `/api/v1/tenants/{tenantId}/api-keys`:

| Method | Action |
|--------|--------|
| GET | List (includes revoked); active first, then revoked |
| POST | Create `{ name, permissions, environment_id?, expires_at? }` → includes `plaintext` once |
| PATCH | Update `{ name?, permissions?, expires_at? }` on non-revoked keys only |
| DELETE | Revoke |

Permission allowlist: `*`, `endpoint_profiles:read`, `endpoint_profiles:write`, `secrets:manage`, `api_keys:manage`, `tenant:admin`.

## Frontend

### Types

Align `ApiKey` with `ApiKeyResource` (`key_prefix`, permissions, environment_id, dates). Add `ApiKeyPayload`.

### ApiKeysPage

- Header CTA “Create API key”.
- Table: name, prefix, permissions, environment, last used, expires, status, actions.
- Inline form for create/edit; environment select on create only.
- One-time plaintext reveal panel after create.
- Revoke confirm dialog.
- Status badges: Active / Expired / Revoked.

### i18n

en + pt-BR under `settings.apiKeys`.

## Out of scope

- Restore/unrevoke
- Changing environment after create
- Secret regeneration
- Separate detail routes
- Pagination

## Deploy

Full deploy (PHP + frontend assets), then hard-refresh smoke on production.

**Note:** SPA catch-all in `routes/web.php` must exclude only `/api` path segments (not paths that merely start with `api`), so `/api-keys` reaches the Vue app.
