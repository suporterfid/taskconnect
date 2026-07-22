# MVP P3 Implementation Plan

**Branch:** `cursor/mvp-p3-allowlist-idempotency-722a`

### Task 1: Tenant allowlists
- Migration `outbound_allow_hosts` on tenants
- Thread hosts through UrlValidator / OutboundPolicy / HttpDeliveryService / EndpointProfileTester / PinnedHttpRequest redirects
- TenantController update + TenantResource
- Unit + feature tests

### Task 2: Success status ranges
- RetryPolicy + RetryDecider::isSuccess(policy)
- AttemptExecutor passes policy
- Unit tests; TaskController validation for ranges

### Task 3: Idempotency middleware
- `EnforceIdempotencyKey` middleware
- Apply to task store + run-now
- Feature tests

### Task 4: Playwright smoke
- `frontend/playwright.config.ts`, `frontend/e2e/login.spec.ts`
- `npm run e2e` script; update CLAUDE.md

### Task 5: CHANGELOG + verify + PR
