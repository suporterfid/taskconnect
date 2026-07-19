# Members CRUD Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship invite/edit-role/remove UI for members on `/members`, with tenant-admin API guards, last-admin protection, and deploy to hub.taskconnect.com.br.

**Architecture:** Extend tenant-scoped Member APIs. MembersPage owns inline invite/edit/remove UX mirroring EnvironmentsPage.

**Tech Stack:** Vue 3 + Pinia + Vue I18n + Laravel API + Docker deploy.

## Global Constraints

- Immediate membership invite (no invitations table).
- Role-only updates.
- Cannot remove self or last tenant_admin.
- Preserve AppLayout / Environments visual patterns (violet CTAs, bordered tables).

---

### Task 1: Design docs

**Files:**
- Create: `docs/superpowers/specs/2026-07-18-members-crud-design.md`
- Create: `docs/superpowers/plans/2026-07-18-members-crud.md`

- [x] Capture approved decisions and task breakdown

### Task 2: Backend

**Files:**
- Create: `app/Application/Members/MemberService.php`
- Create: `app/Policies/MemberPolicy.php`
- Modify: `app/Http/Controllers/Api/V1/MemberController.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Phase1/MemberFeatureTest.php`

- [ ] MemberService invite/update/remove with guards
- [ ] MemberPolicy viewAny/create/update/delete
- [ ] Controller store/update/destroy + audit
- [ ] Feature tests

### Task 3: Types + i18n

**Files:**
- Modify: `frontend/src/services/types.ts`
- Modify: `frontend/src/i18n/locales/en/settings.json`
- Modify: `frontend/src/i18n/locales/pt-BR/settings.json`

- [ ] `TenantRole`, `MemberPayload`
- [ ] Form/role/remove strings en + pt-BR

### Task 4: MembersPage UI

**Files:**
- Modify: `frontend/src/pages/MembersPage.vue`

- [ ] Table + role badges + Edit/Remove
- [ ] Invite/edit inline form
- [ ] Remove confirm → DELETE

### Task 5: Build + deploy

- [ ] Frontend build
- [ ] Full deploy (PHP + assets)
- [ ] Smoke: `/members` invite/edit/remove

### Task 6: Verification

- [ ] Invite existing + new user
- [ ] Role change; last-admin demote fails
- [ ] Remove; self-remove and last-admin remove fail
- [ ] Non-admin cannot write
