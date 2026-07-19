# API Keys CRUD Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship create/edit/revoke UI for API keys on `/api-keys`, show revoked keys with a badge, one-time secret reveal on create, and deploy to hub.taskconnect.com.br.

**Architecture:** Extend tenant-scoped ApiKey APIs (index includes revoked, new PATCH). ApiKeysPage owns inline create/edit/revoke UX mirroring EnvironmentsPage.

**Tech Stack:** Vue 3 + Pinia + Vue I18n + Laravel API + Docker deploy.

## Global Constraints

- Do not add restore/unrevoke.
- Environment immutable after create.
- Plaintext only on create response.
- Preserve AppLayout / Environments visual patterns (violet CTAs, bordered tables).

---

### Task 1: Design docs

**Files:**
- Create: `docs/superpowers/specs/2026-07-18-api-keys-crud-design.md`
- Create: `docs/superpowers/plans/2026-07-18-api-keys-crud.md`

- [x] Capture approved decisions and task breakdown

### Task 2: Backend

**Files:**
- Modify: `app/Http/Controllers/Api/V1/ApiKeyController.php`
- Modify: `app/Application/ApiKeys/ApiKeyService.php`
- Modify: `app/Policies/ApiKeyPolicy.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Phase1/ApiKeyFeatureTest.php`

- [ ] Index includes revoked; sort active first
- [ ] Permission allowlist on store/update
- [ ] PATCH update name/expires_at/permissions; reject revoked
- [ ] Policy `update`; audit `api_key.updated`
- [ ] Feature tests

### Task 3: Types + i18n

**Files:**
- Modify: `frontend/src/services/types.ts`
- Modify: `frontend/src/i18n/locales/en/settings.json`
- Modify: `frontend/src/i18n/locales/pt-BR/settings.json`

- [ ] Fix `ApiKey`; add `ApiKeyPayload`
- [ ] Form/status/revoke/plaintext strings en + pt-BR

### Task 4: ApiKeysPage UI

**Files:**
- Modify: `frontend/src/pages/ApiKeysPage.vue`

- [ ] Table + badges + Edit/Revoke
- [ ] Create/edit inline form with permission checklist
- [ ] One-time plaintext reveal + copy
- [ ] Revoke confirm → DELETE

### Task 5: Build + deploy

- [x] Frontend build
- [x] Full deploy (PHP + assets)
- [x] Fix SPA catch-all so `api-keys` is not treated as `/api` (`routes/web.php`)
- [x] Smoke: `/api-keys` returns 200; `ApiKeysPage` asset live
- [ ] PHP feature tests for API keys (blocked: local sqlite RefreshDatabase missing migrations — pre-existing)

### Task 6: Verification

- [ ] Create → plaintext once → list without plaintext
- [ ] Edit name/expiry/permissions
- [ ] Revoke → badge; PATCH revoked fails
- [ ] List sorts active above revoked
