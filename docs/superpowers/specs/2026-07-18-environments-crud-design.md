# Environments CRUD Design

**Date:** 2026-07-18  
**Status:** Approved  
**Site:** https://hub.taskconnect.com.br/environments

## Problem

The Environments page is read-only. The API already supports create, update, and soft-archive. Archived environments still appear in the header selector without distinction.

## Decisions

- **UI approach:** Inline CRUD on a single `/environments` page (list + create/edit form panel).
- **Archive visibility:** Archived environments remain on the Environments page with an **Archived** badge.
- **Header selector:** Only non-archived environments appear in the AppLayout environment dropdown.
- **After archive of current env:** Switch selection to the first active environment (or `null`).
- **No restore/unarchive** in this pass.
- **No GET show endpoint** required; list + form is enough.

## Backend

Reuse existing routes under `/api/v1/tenants/{tenantId}/environments`:

| Method | Action |
|--------|--------|
| GET | List (includes archived) |
| POST | Create `{ name, slug? }` |
| PATCH | Update `{ name?, slug? }` |
| DELETE | Soft-archive |

Optional polish: sort active first, then archived by name (UI can sort if backend left as-is).

## Frontend

### Types

Extend `Environment` with `archived_at`, `created_at`, `updated_at`. Add `EnvironmentPayload`.

### Tenant store

- Keep full environment list in store (including archived) for the Environments page if needed, **or** page loads its own list and store stays selector-focused.
- Prefer: store `fetchEnvironments` loads all; expose `activeEnvironments` computed for the header select; page uses full list with badges.
- After create/update/archive: call `fetchEnvironments` so the header stays in sync.
- When current env is archived or missing: select first active env.

### EnvironmentsPage

- Header CTA “New environment”.
- Table: name, slug, status badge (Active / Archived), actions (Edit if not archived, Archive if not archived).
- Inline form for create/edit: name + optional slug (auto-slug from name when empty on create).
- Archive confirm dialog.
- Refresh store after mutations.

### i18n

en + pt-BR: form labels, archive confirm, badges, errors. Reuse `common.save` / `cancel` / `edit`.

## Out of scope

- Restore/unarchive
- Enforcing “no new tasks/keys on archived envs” in middleware
- Separate detail routes
- Pagination

## Deploy

Docker-build frontend, FTPS `public/build`, SSH `view:clear`, hard-refresh smoke on production.
