# MVP P3 Design — Allowlists, Success Policy, Idempotency, E2E Smoke

## Goal

Ship the deferred P2+ items that close remaining MVP/spec gaps without reintroducing GitHub Actions.

## Scope

1. **Tenant outbound allowlists** — store `outbound_allow_hosts` on `tenants`; merge into SSRF host allowlisting (same semantics as platform/testing allowlists: may permit private DNS resolution, never bypass port/scheme policy).
2. **Per-task success status ranges** — extend `RetryPolicy` with `success_status_ranges`; `RetryDecider::isSuccess` honors them (default still 200–299).
3. **API Idempotency-Key** — middleware using `idempotency_keys` for task `store` and `run-now` when header present.
4. **Playwright smoke** — wire minimal config + login-page smoke; npm script; no CI.

## Deferred further

- Full multi-step Playwright journey
- Tenant admin UI for allowlist editing (API + model first; optional thin settings field if cheap)
- Custom success-status UI in wizard (API/domain first; expose in TaskResource)

## Approach

- Migration adds `tenants.outbound_allow_hosts` JSON nullable.
- `OutboundPolicy::validateUrl(string $url, array $additionalAllowHosts = [])`.
- `PinnedHttpRequest` carries `additionalAllowHosts` for redirect revalidation.
- Tenant PATCH accepts `outbound_allow_hosts` for tenant admins / platform admins.
- Idempotency middleware keyed by `(tenant_id, route, key)` with body hash + cached JSON response.
