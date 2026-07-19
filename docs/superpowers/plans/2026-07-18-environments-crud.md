# Environments CRUD Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship create/edit/archive UI for environments on `/environments`, keep archived visible with a badge, exclude archived from the header selector, and deploy to hub.taskconnect.com.br.

**Architecture:** Mostly frontend against existing tenant-scoped Environment APIs. Tenant store gains an `activeEnvironments` computed for the header; EnvironmentsPage owns inline create/edit/archive UX.

**Tech Stack:** Vue 3 + Pinia + Vue I18n + Laravel API (existing) + Docker FTPS deploy.

## Global Constraints

- Do not add restore/unarchive.
- Archived stay on Environments page with badge; header selector is active-only.
- After mutations, refresh tenant environments.
- Preserve existing AppLayout / list visual patterns (violet CTAs, bordered tables).

---

### Task 1: Types + i18n

**Files:**
- Modify: `frontend/src/services/types.ts`
- Modify: `frontend/src/i18n/locales/en/environments.json`
- Modify: `frontend/src/i18n/locales/pt-BR/environments.json`

- [ ] Add `archived_at`, `created_at`, `updated_at` to `Environment`; add `EnvironmentPayload`
- [ ] Add form/archive/badge/error strings in en + pt-BR

### Task 2: Tenant store + header selector

**Files:**
- Modify: `frontend/src/stores/tenant.ts`
- Modify: `frontend/src/layouts/AppLayout.vue`

- [ ] Add `activeEnvironments` computed (no `archived_at`)
- [ ] On fetch: if current env missing or archived, select first active
- [ ] Header `<select>` iterates `activeEnvironments`

### Task 3: EnvironmentsPage CRUD UI

**Files:**
- Modify: `frontend/src/pages/EnvironmentsPage.vue`

- [ ] List table with name, slug, Active/Archived badge, Edit/Archive actions
- [ ] Create CTA + inline form (name, optional slug)
- [ ] Edit hydrates form; submit POST or PATCH
- [ ] Archive with confirm → DELETE; then `tenant.fetchEnvironments`
- [ ] Loading/error/empty + needs-tenant states

### Task 4: Build + deploy

- [ ] `docker run … npm run build` for frontend
- [ ] FTPS mirror `public/build` via `taskconnect-deploy`
- [ ] SSH `view:clear`
- [ ] Smoke: HTML references new index hash; create/edit/archive works after hard refresh

### Task 5: Verification

- [ ] Create env → appears in list and header
- [ ] Edit name/slug
- [ ] Archive → badge on page; gone from header; if it was current, selector moves to another active env
