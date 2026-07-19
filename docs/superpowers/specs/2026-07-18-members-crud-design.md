# Members CRUD Design

**Date:** 2026-07-18  
**Status:** Approved  
**Site:** https://hub.taskconnect.com.br/members

## Problem

The Members page is read-only. Spec requires tenant admins to invite members, change roles, and remove members, with audit events.

## Decisions

- **Invite model (MVP):** Immediate membership — no invitations table or accept-token flow.
  - `POST` with `{ email, name?, role }`
  - Existing user → add membership (422 if already a member)
  - New user → create user with random password, create membership, send password-reset link
- **Update:** Role only. Email/name are not edited from this surface.
- **Remove:** Hard-delete the `tenant_memberships` row.
- **Auth:** Tenant admin or platform admin (`actorIsTenantAdmin`). List remains available to any tenant member.
- **Guards:** Cannot remove yourself; cannot demote or remove the last `tenant_admin`.
- **UI:** Inline CRUD on a single `/members` page, matching Environments / API Keys.
- **Roles:** `tenant_admin` | `tenant_member` | `read_only_viewer`

## Backend

Routes under `/api/v1/tenants/{tenantId}/members`:

| Method | Action |
|--------|--------|
| GET | List members (any tenant access) |
| POST | Invite / create membership |
| PATCH | Update role on membership |
| DELETE | Remove membership |

Audit events: `member.invited`, `member.role_changed`, `member.removed`.

## Frontend

### Types

`Member` with `id`, `name`, `email`, `role`. Add `MemberPayload` and `TenantRole` union.

### MembersPage

- Header CTA “Invite member”.
- Table: name, email, localized role, Edit / Remove.
- Inline invite form: email, name, role.
- Inline edit form: role only.
- Remove confirm dialog.

### i18n

en + pt-BR under `settings.members`.

## Out of scope

- Pending invitation tokens / accept-invite UI
- Public self-registration
- Editing user name/email from members page
- Pagination
- API-key-scoped member management

## Deploy

Full deploy (PHP + frontend assets), then hard-refresh smoke on production.
