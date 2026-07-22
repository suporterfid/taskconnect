# Playwright operator E2E (DLQ + pipelines)

Unauthenticated smoke always runs. Authenticated journeys need credentials **and** workspace fixtures.

## Run

```bash
# Browsers (once, in node container / host with frontend deps)
bash ./scripts/tc.sh npm --prefix frontend exec -- playwright install --with-deps chromium

# Unauthenticated only (default CI-safe)
bash ./scripts/tc.sh e2e

# Authenticated (login + optional DLQ/pipelines depth)
E2E_EMAIL='admin@example.com' E2E_PASSWORD='ChangeMeNow!' bash ./scripts/tc.sh e2e
```

`PLAYWRIGHT_BASE_URL` defaults to `http://localhost:8080` (`frontend/playwright.config.ts`).

## Required env

| Variable | Purpose |
|----------|---------|
| `E2E_EMAIL` / `E2E_PASSWORD` | Operator login (Sanctum SPA) |
| (optional) `PLAYWRIGHT_BASE_URL` | Override app URL |

Without `E2E_*`, authenticated tests **skip**; unauthenticated specs still pass.

## Seed fixtures

Authenticated DLQ/pipelines specs **skip** their deep steps when the workspace has no dead runs or pipeline instances. Seed against the same user/tenant the SPA selects after login:

### Dead letter row

1. Create an active task whose delivery fails permanently (e.g. `max_attempts: 1` and a 4xx target, or block via egress).
2. Queue a manual run and execute the scheduler tick until the run is `dead`:

```bash
bash ./scripts/tc.sh artisan scheduler:execute-due
# or claim+execute via your usual cron path
```

3. Confirm `GET …/environments/{env}/dlq` lists the run.

### Pipeline instance

```bash
# After login as the E2E user, or via API key with Idempotency-Key:
# POST /api/v1/tenants/{ten}/environments/{env}/pipelines/{template}/instances
# with a `nodes` map covering every template node (see docs/architecture/pipelines.md).
```

The SPA **Pipelines** page should show at least one instance id link.

## Specs

| File | Coverage |
|------|----------|
| `frontend/e2e/smoke.spec.ts` | Login smoke; optional dashboard/tasks/dlq/pipelines headings |
| `frontend/e2e/operator-extension.spec.ts` | Unauthenticated `/dlq` `/pipelines` → login |
| `frontend/e2e/dlq-pipelines.spec.ts` | Authenticated inspect + replay; pipeline instance detail |
