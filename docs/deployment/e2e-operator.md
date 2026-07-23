# Playwright operator E2E (DLQ + pipelines)

Unauthenticated smoke always runs. Authenticated journeys need credentials; fixtures (a dead
run + a pipeline instance) are now **auto-seeded** (issue #79) — no manual setup required.

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

## Auto-seeded fixtures

`frontend/e2e/helpers/seed.ts` runs once per `dlq-pipelines.spec.ts` suite (`test.beforeAll`)
when `E2E_*` is set. It logs in as the E2E operator via the API (Sanctum cookie + CSRF, same
flow as the SPA), discovers that operator's first tenant/environment (mirroring
`frontend/src/stores/tenant.ts`'s own selection), then:

1. **Dead letter row** — calls a **test-only** seed endpoint,
   `POST …/environments/{env}/e2e/dlq-fixture`, which directly creates a task + a `dead`
   `TaskRun` + a terminal `TaskRunAttempt` (bypassing the scheduler — there's no HTTP-only way
   to drive a real cron tick from Playwright). Gated by the `e2e.testing.only` middleware to
   `APP_ENV=local` or `testing`; 404s otherwise, so it never ships reachable on a real deploy.
2. **Pipeline instance** — calls the real, public
   `POST …/environments/{env}/pipelines/convert-index-publish/instances` endpoint with a
   `nodes` map covering every template node (see `docs/architecture/pipelines.md`).

If seeding fails (e.g. the target isn't running with `APP_ENV=local`/`testing`), the suite
fails loudly with a diagnostic error rather than silently skipping — authenticated journeys
are expected to **run**, not skip, whenever credentials are configured.

To seed manually instead (e.g. exploring the UI by hand), hit the same two endpoints with a
browser dev-tools session or `curl` with the operator's cookies, or use the CLI:

```bash
bash ./scripts/tc.sh artisan tasks:dlq:list   # confirms existing dead runs
```

## Specs

| File | Coverage |
|------|----------|
| `frontend/e2e/smoke.spec.ts` | Login smoke; optional dashboard/tasks/dlq/pipelines headings |
| `frontend/e2e/operator-extension.spec.ts` | Unauthenticated `/dlq` `/pipelines` → login |
| `frontend/e2e/dlq-pipelines.spec.ts` | Authenticated inspect + replay; pipeline instance detail |
