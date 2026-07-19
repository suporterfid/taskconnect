# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

TaskConnect is an open-source, multi-tenant HTTP task scheduler designed to run on PHP/MySQL shared hosting. It's a Laravel 12 backend (modular, DDD-ish layering) plus a Vue 3 SPA in `frontend/`. Cron fires artisan commands every minute; the scheduler claims due work from MySQL and delivers at-least-once HTTP requests to user endpoints with SSRF protection and encrypted secrets.

Product spec: `docs/http-task-scheduler-spec.md`. Deployment docs: `docs/deployment/`.

## Hard constraints (non-negotiable)

These are project invariants. Do not violate them, and reject or flag any change (or dependency) that would.

### 1. Must stay deployable on commodity shared hosting

The application must run, in production, on a plain shared web-hosting plan (e.g. Hostinger, cPanel/LiteSpeed) whose only capabilities are: **PHP 8.2+, MySQL 8.0+, and a per-minute cron**. Document root is `public/`.

- **No dependency on an always-on process, daemon, or external broker/worker.** That rules out Redis, Memcached, RabbitMQ, Beanstalkd, Kafka, Laravel Horizon, Reverb/websocket servers, Supervisor-managed `queue:work` workers, Octane, and similar. None of these may become *required* to run the app.
- **No dependency on a paid managed cloud service or a VPS** to perform core functionality. A user on a cheap shared plan must be able to run the full product.
- Async, scheduled, and retry work must use the existing **MySQL-backed claiming + per-minute cron** pattern (`scheduler:*` commands, `DueTaskClaimer`/`RetryClaimer` leases). Extend that mechanism rather than reaching for a queue broker.
- Config defaults must keep `QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` on database/file-backed stores. A Redis (or other broker) driver may only ever be an **optional, opt-in** enhancement — never the default and never required.
- Any PHP extension or tool assumed by the code must be one commonly available on shared hosting; if a change needs something unusual, call it out explicitly.

When a feature seems to need a background worker or broker, design it around cron-driven DB claiming instead — or surface the trade-off rather than silently adding infrastructure.

### 2. Track all work on GitHub issues

Every unit of work — feature requests, user stories, implementation plans, tasks, and bug reports — must be represented and kept current as a **GitHub issue** in `suporterfid/taskconnect`, not only in local docs or commit messages.

- Before starting non-trivial work, open (or find) the corresponding issue describing the request/story/bug and the plan.
- Keep it updated as work progresses: reflect status changes, link the PR(s) that address it, and close it with a clear reason when done.
- Keep any planning docs under `docs/` in sync with the issue; the issue is the canonical, up-to-date record.

## Development is Docker-only

**Do not install or run PHP, Composer, Node, or npm on the host.** Everything runs through containers via the `tc` wrapper (`scripts/tc.sh` on Linux/macOS, `scripts/tc.ps1` on Windows; `Makefile` proxies to `tc.sh`).

```bash
cp .env.example .env
./scripts/tc.sh up            # start app, mysql, mailpit, receiver
./scripts/tc.sh bootstrap     # composer install, key:generate, migrate, npm ci
./scripts/tc.sh artisan platform:bootstrap-admin admin@example.com 'ChangeMeNow!' --name=Admin
```

- App: http://localhost:8080 · Mailpit UI: http://localhost:8025 · `receiver` (test HTTP target used by outbound tests): http://localhost:8090

### Common commands

| Command | Purpose |
|---|---|
| `./scripts/tc.sh test` | Full backend PHPUnit suite (inside app container) |
| `./scripts/tc.sh artisan <cmd>` | Any artisan command |
| `./scripts/tc.sh composer <cmd>` | Composer in app container |
| `./scripts/tc.sh npm <cmd>` | npm in node container (dev profile) |
| `./scripts/tc.sh shell` | Shell in app container |
| `./scripts/tc.sh release` | Build shared-hosting zip under `dist/` |

Run a single backend test through the container, e.g.:
```bash
./scripts/tc.sh artisan test --filter=ScheduleCalculatorTest
./scripts/tc.sh artisan test tests/Feature/Scheduling/SchedulerClaimingTest.php
```

Frontend tests/build (Vitest + `vue-tsc`), run through the node container:
```bash
./scripts/tc.sh npm --prefix frontend run test
./scripts/tc.sh npm --prefix frontend run build
```

Tests use SQLite `:memory:` (see `phpunit.xml`); the running app uses MySQL. Keep migrations/queries portable enough to pass under SQLite.

### CI

`.github/workflows/ci.yml` runs the whole thing through `compose.ci.yaml` (sets `TC_CI=1`): builds images, waits for MySQL, `composer install`, `migrate`, `artisan test`, then `npm ci && npm test && npm run build`. Mirror these steps locally before pushing.

If Packagist is unreachable, set `COMPOSER_PACKAGIST_URL` for the session only — **never commit mirror configuration** (`tc.sh` warns when it's set).

## Architecture

### Layered backend (`app/`)

The code is deliberately split by responsibility, not by Laravel's default convention:

- **`app/Domain/`** — pure business logic, framework-free. State machines (`RunStateMachine`, `AttemptStateMachine`), enums (`RunState`, `AttemptState`, `TriggerType`, `TaskDefinitionStatus`), schedule math (`ScheduleCalculator`, `ScheduleConfig`), retry logic (`RetryDecider`, `RetryPolicy`), and the SSRF outbound policy (`Domain/Execution/Outbound/`). No Eloquent, no facades here.
- **`app/Application/`** — orchestration services that wire Domain logic to persistence, grouped by feature (`Scheduling/`, `Execution/`, `Tasks/`, `Secrets/`, `Members/`, `Tenancy/`, `ApiKeys/`, `EndpointProfiles/`, `Audit/`, `Notifications/`, `Retention/`). These are the transaction boundaries.
- **`app/Infrastructure/`** — concrete adapters: `Persistence/Eloquent/` (all Eloquent models live here, **not** `app/Models/` — only `User` is a stub there), `HttpClient/` (DNS-pinned Guzzle transport), `Dns/`.
- **`app/Http/`** — thin controllers under `Controllers/Api/V1/`, plus `Resources/`, `Middleware/`, `Support/ApiErrorRenderer`.

Services and domain collaborators are bound (mostly as singletons) in `app/Providers/AppServiceProvider.php`. `Clock`, `DnsResolverInterface`, and `OutboundPolicy` are interfaces/factories bound there — depend on the interface and inject via constructor rather than `new`-ing or using facades in Domain/Application code. Model→policy mappings are in `AuthServiceProvider`.

### Multi-tenancy

The hierarchy is **Tenant → Environment → (Secrets, EndpointProfiles, Tasks → TaskRuns → TaskRunAttempts)**. This is reflected in the nested API routes (`routes/api.php`, all under `/v1`). Two custom middleware enforce it:

- `auth.api_or_sanctum` (`AuthenticateApiKeyOrSanctum`) — accepts either a Sanctum session (SPA) or an API key.
- `tenant.context` (`ResolveTenantEnvironment`) — resolves and authorizes the `{tenantId}`/`{environmentId}` route params.

Authorization is per-resource via Policies (`app/Policies/`) keyed on `TenantRole`. Tenant-isolation is explicitly tested (`tests/Feature/Phase0/…IsolationTest`, `tests/Feature/Phase1/…IsolationTest`) — new tenant-scoped features must not leak across tenants.

### Scheduler (the core loop)

Three artisan commands are meant to run from cron every minute (see `docs/deployment/cron.md`):

- `scheduler:execute-due` → claims and runs due scheduled tasks
- `scheduler:retry-due` → claims and runs due retry attempts
- `scheduler:maintenance` → recovers stale claims + retention cleanup (hourly)

Each delegates to `SchedulerCycleRunner`. Claiming is **DB-backed, not queue-backed**: `DueTaskClaimer`/`RetryClaimer` use a `DB::transaction` + `lockForUpdate` + a `claim_token`/`claim_expires_at` lease so concurrent cron invocations (or multiple servers) don't double-run a task. `StaleClaimRecovery` reclaims leases whose TTL expired. Delivery is **at-least-once**; idempotency/occurrence keys (`IdempotencyKeyGenerator`, `OccurrenceKeyGenerator`) guard duplicates. Batch sizes and TTL come from `config/scheduler.php` (`SCHEDULER_*` env vars).

### Outbound HTTP safety (SSRF)

All outbound calls go through `Domain/Execution/Outbound/OutboundPolicy` (validates URL, resolves + classifies IPs, blocks link-local/metadata addresses) and the DNS-pinned transport in `Infrastructure/HttpClient/` (resolves the host once, then connects to that exact IP to defeat DNS rebinding). Policy is configured in `config/outbound.php`: allowed ports, `allow_http`, cloud metadata IP/host blocklists, and — for local/testing only — `testing_allow_hosts` (defaults to the `receiver` container). Never widen these blocklists or bypass the pinned transport for user-supplied URLs.

### Secrets

User secrets are encrypted at rest (`Application/Secrets/SecretService`) and redacted from logs/snapshots via `Domain/Secrets/SecretRedactor` and `Application/Execution/RequestSnapshotRedactor`. When adding fields that may carry secret material to request/response snapshots, ensure they pass through redaction.

### Frontend (`frontend/`)

Vue 3 + TypeScript + Vite + Pinia + vue-router + vue-i18n + Tailwind v4. `src/pages/` are route views, `src/stores/` (`auth`, `tenant`, `locale`) hold app state, `src/services/api.ts` is the typed axios client against the `/v1` API, `src/services/types.ts` mirrors API shapes. Built assets are served by Laravel from `public/`. E2E uses Playwright (Chromium is pre-installed at `/opt/pw-browsers` — do not run `playwright install`).

## Conventions

- Domain and Application code should stay framework-light: constructor-inject dependencies, prefer the bound interfaces (`Clock`, `DnsResolverInterface`, `OutboundPolicy`) over facades and `new`. Time comes from `Clock` so it can be frozen in tests.
- New Eloquent models go in `app/Infrastructure/Persistence/Eloquent/`, not `app/Models/`.
- Feature work is organized in "phases" (see `tests/Feature/Phase0`, `Phase1` and `docs/superpowers/`); follow the existing plan/spec docs when extending a feature area.
- Production target is shared hosting with document root `public/` and cron — avoid features that assume a long-running queue worker or daemon (see **Hard constraints** above).
