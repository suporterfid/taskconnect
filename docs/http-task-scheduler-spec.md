# Open-Source Multi-Tenant HTTP Task Scheduler

## Product and Technical Specification

### Document Metadata

- **Version:** 1.1
- **Status:** Implementation-ready MVP specification
- **Target audience:** AI coding agents, software architects, maintainers, and contributors
- **Primary deployment target:** PHP and MySQL-compatible shared hosting with cron access
- **Reference deployment profile:** Hostinger-like shared hosting without a VPS, Redis, long-running workers, or process supervisors
- **Initial interface languages:** English (`en`) and Brazilian Portuguese (`pt-BR`)

---

## 1. Executive Summary

This product is an open-source, multi-tenant platform for defining, scheduling, and delivering outbound HTTP requests. Users configure an HTTP destination, request data, retry policy, timezone, and human-friendly schedule. A short-lived PHP process invoked by cron identifies due work, safely claims it, performs the outbound requests, and records the results.

The product is intentionally optimized for simple, low-cost deployment rather than real-time precision or high-throughput distributed processing. Its primary runtime consists of:

- a PHP web application;
- a REST API;
- a Vue-based frontend compiled to static assets;
- a MySQL-compatible database; and
- one or more short-lived cron entry points.

The MVP provides best-effort, minute-level scheduling with at-least-once delivery semantics. It must remain reliable when cron starts late, a process is interrupted, or two executor processes overlap.

---

## 2. Normative Language

The terms **MUST**, **MUST NOT**, **SHOULD**, **SHOULD NOT**, and **MAY** express requirement priority:

- **MUST / MUST NOT:** mandatory for the stated release;
- **SHOULD / SHOULD NOT:** strongly recommended unless a documented reason justifies a deviation;
- **MAY:** optional.

Unless explicitly marked as deferred, requirements in this document apply to the MVP.

---

## 3. Product Goals

### 3.1 Primary Goals

The product MUST:

- schedule and execute outbound HTTP requests using PHP, a MySQL-compatible database, and cron;
- run without a VPS, Redis, RabbitMQ, a process supervisor, or a long-running queue worker;
- support multiple tenants in one deployment;
- support multiple environments within each tenant;
- expose a versioned REST API for the frontend and third-party integrations;
- provide a polished interface that does not require users to understand cron syntax;
- provide execution history, retry visibility, and actionable diagnostics;
- support English and Brazilian Portuguese from the first release;
- use safe defaults for outbound network access, secrets, retries, and logging; and
- preserve a practical upgrade path to a future hosted SaaS and worker-based execution model.

### 3.2 Secondary Goals

The product SHOULD:

- be installable by a technically capable user who is not an infrastructure specialist;
- minimize operational dependencies and maintenance overhead;
- use a code structure that AI coding agents can navigate and extend reliably;
- allow agencies and integrators to operate isolated customer workspaces; and
- make future infrastructure replacement possible without redesigning the public API or core domain model.

---

## 4. Non-Goals

The MVP does not provide:

- second-level or real-time execution guarantees;
- exactly-once delivery;
- distributed queue partitions or horizontal worker orchestration;
- streaming task execution;
- event sourcing;
- arbitrary shell, PHP, JavaScript, or container execution;
- a visual workflow builder;
- dependency graphs between tasks;
- usage-based billing;
- marketplace integrations;
- advanced cron-expression editing in the default UI;
- guaranteed execution of every missed historical occurrence; or
- large-response archival.

These capabilities may be considered after the shared-hosting MVP is stable.

---

## 5. Product Principles

1. **Deployment simplicity over infrastructure sophistication.**
2. **Human-friendly scheduling over cron-centric configuration.**
3. **Safe defaults over unrestricted flexibility.**
4. **Strong tenant isolation from the first migration.**
5. **At-least-once delivery with explicit idempotency support.**
6. **Progressive disclosure in the user interface.**
7. **Observable failures instead of silent failures.**
8. **Clear domain boundaries instead of controller-driven business logic.**
9. **Portable core logic instead of framework-specific coupling.**
10. **Explicit tradeoffs instead of implied real-time guarantees.**

---

## 6. Target Users

### 6.1 Primary Users

- Developers who need scheduled webhooks or API calls without operating queue infrastructure.
- Technical operators running internal automation on low-cost hosting.
- Agencies and systems integrators managing isolated automations for multiple clients.
- Small SaaS teams that need an open-source starting point with a future SaaS migration path.

### 6.2 Secondary Users

- Product teams that need scheduled callbacks.
- Non-developer administrators using preconfigured endpoint profiles.
- Support engineers troubleshooting failed webhook delivery.

---

## 7. Core Domain Model

The implementation MUST distinguish the following concepts.

### 7.1 Tenant

A tenant is an isolated workspace. Tenant-owned records MUST NOT be visible or modifiable outside the active tenant context.

### 7.2 Environment

An environment is a tenant-scoped logical boundary such as Development, Staging, or Production. Tasks, endpoint profiles, secrets, API keys, and execution history MUST be environment-scoped unless explicitly documented otherwise.

### 7.3 Endpoint Profile

An endpoint profile is a reusable destination configuration containing a base URL, default HTTP settings, and references to secrets.

### 7.4 Task

A task is a reusable definition of what request to send and when to send it. A task is not an individual execution.

### 7.5 Scheduled Occurrence

A scheduled occurrence is one intended execution time produced by the schedule engine.

### 7.6 Run

A run represents one logical execution of a task for a scheduled occurrence or manual trigger. A run may contain multiple delivery attempts.

### 7.7 Attempt

An attempt is one actual outbound HTTP request. Retries create additional attempts within the same run.

### 7.8 Secret

A secret is encrypted sensitive data referenced by endpoint profiles or tasks. Secret plaintext MUST never be returned after creation.

---

## 8. Roles and Authorization

### 8.1 Platform Roles

- **Platform super admin:** manages the deployment and may access all tenants for support and administration.

### 8.2 Tenant Roles

- **Tenant admin:** manages tenant settings, members, environments, API keys, endpoint profiles, secrets, and tasks.
- **Tenant member:** creates and operates tasks within permitted environments.
- **Read-only viewer:** views configuration and execution history without changing it.

### 8.3 Authorization Requirements

- Every tenant-scoped request MUST resolve an authenticated tenant context.
- Every environment-scoped request MUST verify that the environment belongs to the active tenant.
- Authorization MUST be enforced in centralized policies or guards, not repeated ad hoc in controllers.
- Record lookup by identifier MUST include tenant scoping in the query or repository boundary.
- API keys MUST have explicit tenant, environment, and permission scopes.

---

## 9. Functional Requirements

### 9.1 Authentication

The MVP MUST support:

- bootstrap creation of the first platform administrator;
- login and logout;
- password reset;
- secure session authentication for the web frontend;
- hashed API keys for machine access;
- API key creation and revocation; and
- optional tenant invitations created by tenant administrators.

Public self-registration is deferred. New tenants are created by the platform administrator in the MVP.

### 9.2 Multi-Tenancy

The system MUST use a shared database with tenant ownership columns.

Requirements:

- every tenant-owned table MUST contain `tenant_id`;
- environment-owned records MUST also contain `environment_id`;
- tenant isolation MUST be covered by automated tests;
- platform-level support access MUST be auditable; and
- cross-tenant uniqueness constraints MUST be scoped appropriately, for example by `(tenant_id, slug)` rather than `slug` alone.

### 9.3 Environments

Each tenant MUST be able to create, rename, archive, and select environments.

Default environments MAY be created during tenant provisioning:

- Development
- Staging
- Production

An archived environment MUST remain readable but MUST NOT allow new runs, task activation, or new API keys.

### 9.4 Endpoint Profiles

An endpoint profile MUST support:

- name and description;
- environment;
- base URL or fixed full URL;
- default HTTP method;
- default headers;
- authentication mode;
- references to encrypted secrets;
- connect timeout and total timeout;
- redirect policy;
- TLS verification policy;
- optional allowed path prefix; and
- enabled or disabled state.

Supported authentication modes in the MVP:

- none;
- static header value stored as a secret;
- bearer token;
- basic authentication;
- query-parameter token.

TLS verification MUST be enabled by default. Disabling TLS verification MUST require tenant-admin permission, display a warning, and be included in audit logs.

### 9.5 Tasks

A task MUST support:

- name and optional description;
- tenant and environment ownership;
- endpoint profile or inline endpoint configuration;
- HTTP method;
- URL path or full URL;
- query parameters;
- headers;
- request body;
- content type;
- schedule;
- timezone;
- retry policy;
- enabled, paused, or draft definition state;
- next scheduled occurrence;
- latest run summary;
- creator and updater identifiers; and
- timestamps.

Both endpoint profiles and inline endpoints are supported in the MVP. The UI SHOULD recommend endpoint profiles when the same destination is reused.

### 9.6 Supported HTTP Requests

The HTTP execution engine MUST support:

- `GET`;
- `POST`;
- `PUT`;
- `PATCH`;
- `DELETE`;
- custom headers;
- query parameters;
- JSON bodies;
- `application/x-www-form-urlencoded` bodies; and
- raw text bodies.

Multipart file uploads are deferred.

### 9.7 Human-Friendly Scheduling

The default user interface MUST support:

- run once at a selected date and time;
- every N minutes;
- hourly at a selected minute;
- daily at a selected time;
- weekly on selected weekdays at a selected time;
- monthly on a selected calendar day at a selected time; and
- business days at a selected time.

Raw cron expressions MUST NOT be exposed in the MVP UI.

The scheduling API MUST use normalized structured data rather than accepting raw cron expressions.

### 9.8 Task Lifecycle Actions

Authorized users MUST be able to:

- save a task as a draft;
- activate a task;
- pause and resume a task;
- duplicate a task;
- run a task manually;
- archive a task; and
- inspect its run and attempt history.

A manual run MUST create a pending run and initial attempt for asynchronous processing by the executor. The web request MUST NOT hold the connection open while performing the destination HTTP call.

Hard deletion of tasks with execution history SHOULD be avoided. The API SHOULD archive them instead.

### 9.9 Endpoint Testing

Before activation, users MUST be able to send a test request.

A test request MUST:

- use the same SSRF validation as normal execution;
- create a traceable test result;
- be clearly distinguished from scheduled and manual runs;
- use a shorter configurable response-body limit;
- not modify `next_run_at`; and
- not automatically apply the task retry policy.

### 9.10 Notifications

The MVP MUST provide:

- in-app failed-task indicators;
- badges for runs awaiting retry;
- a dashboard count of dead runs; and
- an optional email notification hook for repeated or terminal failures.

A full notification rule builder is deferred.

---

## 10. Scheduling Semantics

### 10.1 Time Storage

- All persisted timestamps MUST use UTC.
- Every recurring schedule MUST store an IANA timezone, for example `America/Sao_Paulo`.
- The user interface MUST display dates and times in the selected user timezone unless the user explicitly selects another timezone.

### 10.2 Next-Occurrence Calculation

The schedule service MUST calculate and persist `next_run_at`.

The executor MUST query the persisted timestamp rather than recalculate every task schedule during each cron cycle.

After a run is claimed, the next future occurrence MUST be calculated independently of the run result. A retry MUST NOT replace or delay the next recurring occurrence.

### 10.3 Missed Occurrences

The MVP uses a **skip-backlog** policy:

- when cron is late, the system creates at most one scheduled run for a task during a claim cycle;
- the run records the original `scheduled_for` timestamp;
- after the run is created, `next_run_at` advances to the first occurrence strictly after the current calculation time; and
- historical missed occurrences are not backfilled.

This policy prevents a long outage from creating an execution storm.

### 10.4 Daylight-Saving Time

For recurring local times:

- if a local time does not exist because clocks move forward, the occurrence MUST run at the next valid local time on that date;
- if a local time occurs twice because clocks move backward, the occurrence MUST run once at the earlier UTC instant; and
- automated tests MUST cover representative daylight-saving transitions.

### 10.5 Monthly Schedules

When a selected calendar day does not exist in a month, the MVP MUST skip that month rather than move the occurrence to the final day of the month.

### 10.6 One-Time Schedules

A one-time task MUST transition to a completed inactive state after its run is created. Retries for that run may continue according to its retry policy.

---

## 11. Delivery and Retry Semantics

### 11.1 Delivery Guarantee

The system provides **at-least-once** delivery.

Duplicate delivery is possible after timeout, process interruption, stale-lock recovery, or retry. The product MUST document this behavior clearly.

### 11.2 Idempotency

Every run MUST have a stable idempotency key. Every attempt in the run MUST send the same key in:

```http
X-Task-Idempotency-Key: <run-idempotency-key>
```

The system SHOULD also send:

```http
X-Task-Run-Id: <public-run-id>
X-Task-Attempt: <attempt-number>
```

Destination systems SHOULD treat the idempotency key as the unique logical delivery identifier.

### 11.3 Success Policy

By default, any HTTP status from `200` through `299` is successful.

A task MAY override the accepted status-code ranges through validated configuration. Redirect responses are not considered successful unless redirects are enabled and the final response satisfies the success policy.

### 11.4 Retry Eligibility

The MVP MUST support retries for:

- connection failures;
- DNS failures;
- TLS negotiation failures;
- timeouts;
- HTTP `408`;
- HTTP `425`;
- HTTP `429`;
- HTTP `500` through `599`; and
- explicitly configured status codes.

The default policy MUST NOT retry other `4xx` responses.

### 11.5 Retry Policy

A retry policy MUST define:

- maximum attempts, including the initial attempt;
- backoff strategy;
- retryable conditions;
- optional maximum retry window; and
- whether a valid `Retry-After` header may override the calculated delay.

Default policy:

| Attempt | Delay from previous attempt |
|---:|---:|
| 1 | Initial delivery |
| 2 | 1 minute |
| 3 | 5 minutes |
| 4 | 15 minutes |
| 5 | 1 hour |
| 6 | 6 hours |

The default maximum is six attempts, including the initial attempt.

### 11.6 Terminal States

A run MUST enter one of these terminal states:

- `succeeded`;
- `dead` after retry exhaustion;
- `cancelled`; or
- `blocked` when policy prevents delivery.

An individual attempt MAY end as:

- `succeeded`;
- `failed_retryable`;
- `failed_terminal`;
- `timed_out`;
- `blocked`; or
- `interrupted`.

---

## 12. Executor Design

### 12.1 Cron Entry Points

The deployment MUST provide these CLI-safe entry points:

```text
php artisan scheduler:execute-due
php artisan scheduler:retry-due
php artisan scheduler:maintenance
```

An implementation using a framework other than Laravel MUST provide equivalent commands.

The hosting panel SHOULD invoke the due-task and retry commands every minute. Maintenance MAY run hourly or daily depending on the operation.

### 12.2 Execution Budget

Default limits:

- maximum claimed scheduled runs per cycle: 20;
- maximum claimed retries per cycle: 20;
- connect timeout: 5 seconds;
- total request timeout: 15 seconds;
- target executor duration: less than 45 seconds;
- response body capture limit: 64 KiB;
- request body capture limit: 64 KiB; and
- redirect limit: 3.

All limits MUST be configurable by the platform administrator.

### 12.3 Claiming Due Tasks

The executor MUST prevent overlapping cron processes from creating duplicate runs for the same scheduled occurrence.

Recommended algorithm:

1. Start a short database transaction.
2. Select a limited batch of active tasks where `next_run_at <= now_utc` and no valid claim exists.
3. Atomically assign a unique `claim_token`, `claimed_at`, and claim expiry.
4. Create one run and its initial pending attempt per successfully claimed task using a unique `(task_id, scheduled_for, trigger_type)` constraint.
5. Advance the task's `next_run_at` to the next future occurrence.
6. Commit the transaction.
7. Execute HTTP requests outside the transaction.

The implementation MAY use `SELECT ... FOR UPDATE SKIP LOCKED` when supported. It MUST provide a safe compare-and-update fallback for compatible database engines without that capability.

### 12.4 Retry Claiming

Retries MUST be claimed from attempts or runs with `next_attempt_at <= now_utc`. Claiming MUST use a unique token and expiry equivalent to scheduled-run claiming.

### 12.5 Stale Claims

- Claim leases SHOULD expire after 10 minutes by default.
- Maintenance MUST release expired claims.
- Recovery MUST mark an in-progress attempt as `interrupted` before scheduling another attempt.
- Recovery MUST preserve the run idempotency key.

### 12.6 Process Interruption

The executor MUST be safe if it stops:

- before creating a run;
- after creating a run and initial attempt but before starting the request;
- while the request is in progress;
- after receiving the response but before persisting it; or
- while calculating a retry.

The implementation MUST favor recoverability over assuming a clean process exit.

---

## 13. Outbound HTTP Security

### 13.1 SSRF Protection

Because users configure server-side outbound requests, SSRF protection is mandatory.

The request validator MUST:

- allow only `http` and `https` schemes;
- reject embedded credentials in URLs;
- reject localhost hostnames;
- reject loopback addresses;
- reject private IPv4 and IPv6 ranges by default;
- reject link-local, multicast, unspecified, and reserved address ranges;
- reject cloud metadata endpoints;
- resolve all destination IPs before connecting;
- revalidate every redirect destination;
- prevent redirects to blocked addresses or schemes;
- limit the number of redirects;
- support platform and tenant domain allowlists; and
- log policy rejections without exposing secrets.

The HTTP client SHOULD connect only to an IP that was validated for the resolved hostname and MUST preserve TLS hostname verification.

### 13.2 Port Policy

By default, outbound requests MUST be limited to ports `80` and `443`.

A platform administrator MAY allow additional ports globally. Tenant administrators MUST NOT bypass the platform port policy.

### 13.3 HTTPS Policy

- HTTPS MUST be the default.
- Plain HTTP MUST require an explicit platform-level setting.
- The UI MUST warn users when configuring an HTTP endpoint.
- TLS certificate verification MUST remain enabled unless explicitly overridden by an authorized administrator.

### 13.4 Header Safety

The system MUST reject or control headers that could interfere with transport behavior, including:

- `Host`;
- `Content-Length`;
- `Transfer-Encoding`;
- `Connection`; and
- proxy-specific headers.

The application MUST generate its own `User-Agent`, for example:

```text
OpenHttpScheduler/1.1
```

---

## 14. Secret Management

### 14.1 Storage

Secrets MUST:

- be encrypted at rest using an application encryption key;
- be stored separately from visible endpoint and task configuration;
- never appear in plaintext database logs;
- never be returned by read APIs after creation;
- be redacted from request snapshots and audit logs; and
- be versionable to support rotation.

### 14.2 Application Key

The installation MUST require a strong application key that is stored outside the public web root. Losing this key may make encrypted secrets unrecoverable and MUST be documented as a backup consideration.

### 14.3 Secret References

Tasks and endpoint profiles MUST store secret references rather than interpolated plaintext values. Secret resolution MUST occur immediately before request execution.

### 14.4 Redaction

The logging layer MUST redact:

- authorization headers;
- configured secret-bearing headers;
- secret query parameters;
- basic-auth credentials;
- bearer tokens; and
- secret values appearing in structured request snapshots.

---

## 15. Data Model

### 15.1 Required Tables

The MVP data model MUST include equivalents of:

- `users`;
- `tenants`;
- `tenant_memberships`;
- `environments`;
- `api_keys`;
- `endpoint_profiles`;
- `secrets`;
- `tasks`;
- `task_schedules`;
- `task_runs`;
- `task_run_attempts`;
- `audit_logs`;
- `system_heartbeats`; and
- `user_preferences`.

### 15.2 Identifier Strategy

Public resources SHOULD use UUIDv7, ULID, or another non-sequential sortable identifier. Internal numeric primary keys MAY be used, but MUST NOT be exposed as the only public identifier.

### 15.3 Tasks

Recommended fields for `tasks`:

```text
id
public_id
tenant_id
environment_id
endpoint_profile_id nullable
name
description nullable
definition_status
method
url_or_path
headers_json
query_json
body_template nullable
content_type nullable
timezone
retry_policy_json
next_run_at nullable
last_run_at nullable
last_run_state nullable
claim_token nullable
claimed_at nullable
claim_expires_at nullable
created_by
updated_by
created_at
updated_at
archived_at nullable
```

### 15.4 Task Schedules

Recommended fields for `task_schedules`:

```text
id
public_id
tenant_id
task_id
schedule_kind
schedule_config_json
starts_at nullable
ends_at nullable
last_calculated_at nullable
created_at
updated_at
```

`tasks.next_run_at` is the executor-facing denormalized timestamp. The structured schedule remains the source for future occurrence calculation.

### 15.5 Task Runs

Recommended fields for `task_runs`:

```text
id
public_id
tenant_id
environment_id
task_id
trigger_type
scheduled_for nullable
idempotency_key
run_state
attempt_count
next_attempt_at nullable
started_at nullable
finished_at nullable
final_http_status nullable
final_error_code nullable
created_at
updated_at
```

### 15.6 Task Run Attempts

Recommended fields for `task_run_attempts`:

```text
id
public_id
tenant_id
environment_id
task_run_id
attempt_number
attempt_state
claim_token nullable
claimed_at nullable
claim_expires_at nullable
started_at nullable
finished_at nullable
duration_ms nullable
request_url_redacted
request_headers_redacted_json
request_body_redacted nullable
response_status nullable
response_headers_json nullable
response_body_truncated nullable
response_body_sha256 nullable
transport_error_code nullable
transport_error_message nullable
next_retry_at nullable
created_at
updated_at
```

### 15.7 API Keys

API keys MUST store:

- a non-secret public prefix;
- a cryptographic hash of the full key;
- tenant and optional environment scope;
- permissions;
- creator;
- last-used timestamp;
- expiry timestamp, optional; and
- revocation timestamp, optional.

The full API key MUST be displayed only once at creation.

### 15.8 Required Constraints and Indexes

At minimum:

```text
UNIQUE task_runs(task_id, scheduled_for, trigger_type)
UNIQUE task_run_attempts(task_run_id, attempt_number)
INDEX tasks(tenant_id, definition_status, next_run_at)
INDEX tasks(claim_expires_at)
INDEX task_runs(tenant_id, environment_id, run_state, next_attempt_at)
INDEX task_runs(tenant_id, task_id, created_at)
INDEX task_run_attempts(task_run_id, attempt_number)
INDEX task_run_attempts(claim_expires_at)
INDEX tenant_memberships(tenant_id, user_id)
INDEX api_keys(key_prefix)
INDEX audit_logs(tenant_id, created_at)
```

For manual and test runs, `scheduled_for` MAY be null. The implementation MUST use an alternate uniqueness rule or a trigger-specific external request identifier to prevent accidental duplicate API submissions.

---

## 16. State Models

### 16.1 Task Definition States

- `draft`
- `active`
- `paused`
- `completed`
- `archived`

### 16.2 Run States

- `pending`
- `running`
- `retry_wait`
- `succeeded`
- `dead`
- `cancelled`
- `blocked`

### 16.3 Attempt States

- `pending`
- `running`
- `succeeded`
- `failed_retryable`
- `failed_terminal`
- `timed_out`
- `interrupted`
- `blocked`

State transitions MUST be implemented in dedicated domain or application services and covered by tests.


## 17. REST API Design

### 17.1 Base Conventions

- Base path: `/api/v1`
- Media type: `application/json`
- Timestamps: ISO 8601 with UTC `Z` suffix in API payloads
- Authentication: secure session for the frontend or API key for automation clients
- Pagination: cursor-based where practical
- Request tracing: every response includes a request identifier
- Errors: stable machine-readable error codes
- Resource identifiers: public non-sequential IDs

### 17.2 Tenant Context

The active tenant and environment MUST be unambiguous.

Preferred API convention:

```text
/api/v1/tenants/{tenantId}/environments/{environmentId}/tasks
```

For frontend convenience, a session MAY store the selected tenant and environment, but backend authorization MUST still validate resource ownership.

### 17.3 Core Resources

```text
/auth
/tenants
/tenant-memberships
/environments
/endpoint-profiles
/secrets
/tasks
/task-runs
/task-run-attempts
/api-keys
/audit-logs
/user-preferences
/platform/health
```

### 17.4 Authentication Endpoints

```http
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
GET  /api/v1/me
```

### 17.5 Tenant and Environment Endpoints

```http
GET    /api/v1/tenants
POST   /api/v1/tenants
GET    /api/v1/tenants/{tenantId}
PATCH  /api/v1/tenants/{tenantId}
GET    /api/v1/tenants/{tenantId}/environments
POST   /api/v1/tenants/{tenantId}/environments
PATCH  /api/v1/tenants/{tenantId}/environments/{environmentId}
DELETE /api/v1/tenants/{tenantId}/environments/{environmentId}
```

`DELETE` for environments SHOULD archive rather than physically delete records.

### 17.6 Endpoint Profile Endpoints

```http
GET    /api/v1/tenants/{tenantId}/environments/{environmentId}/endpoint-profiles
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/endpoint-profiles
GET    /api/v1/tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}
PATCH  /api/v1/tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}
DELETE /api/v1/tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/endpoint-profiles/{profileId}/test
```

### 17.7 Task Endpoints

```http
GET    /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks
GET    /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}
PATCH  /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}
DELETE /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/activate
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/pause
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/resume
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/run-now
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/test
POST   /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks/{taskId}/duplicate
```

### 17.8 Run Endpoints

```http
GET  /api/v1/tenants/{tenantId}/environments/{environmentId}/task-runs
GET  /api/v1/tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}
GET  /api/v1/tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}/attempts
POST /api/v1/tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}/cancel
POST /api/v1/tenants/{tenantId}/environments/{environmentId}/task-runs/{runId}/retry
```

Manual retry MUST create a new attempt in the same run unless the user explicitly creates a new manual run.

### 17.9 API Key Endpoints

```http
GET    /api/v1/tenants/{tenantId}/api-keys
POST   /api/v1/tenants/{tenantId}/api-keys
DELETE /api/v1/tenants/{tenantId}/api-keys/{apiKeyId}
```

### 17.10 HTTP Status Codes

The API SHOULD use:

- `200 OK` for successful reads and actions returning a representation;
- `201 Created` for newly created resources;
- `202 Accepted` when a manual execution is queued for cron processing;
- `204 No Content` for successful revocation or archival without a response body;
- `400 Bad Request` for malformed input;
- `401 Unauthorized` for missing or invalid authentication;
- `403 Forbidden` for insufficient permission;
- `404 Not Found` for missing or inaccessible tenant-scoped resources;
- `409 Conflict` for invalid state transitions or duplicate submissions;
- `422 Unprocessable Entity` for validation errors;
- `429 Too Many Requests` for API rate limits; and
- `500 Internal Server Error` for unexpected server failures.

### 17.11 Error Envelope

```json
{
  "error": {
    "code": "validation_error",
    "message": "The request is invalid.",
    "details": {
      "url": ["The URL field is required."]
    },
    "request_id": "req_01JXYZ..."
  }
}
```

User-facing messages MAY be localized. The `code` field MUST remain stable and language-neutral.

### 17.12 Create Task Example

```json
{
  "name": "Notify CRM daily",
  "endpoint_profile_id": "ep_01JXYZ...",
  "method": "POST",
  "path": "/hooks/daily-sync",
  "headers": {
    "X-System": "scheduler"
  },
  "body": {
    "scope": "daily"
  },
  "content_type": "application/json",
  "schedule": {
    "kind": "daily_at",
    "timezone": "America/Sao_Paulo",
    "time": "09:00"
  },
  "retry_policy": {
    "max_attempts": 6,
    "strategy": "standard_exponential"
  },
  "definition_status": "active"
}
```

### 17.13 Task Response Example

```json
{
  "data": {
    "id": "task_01JXYZ...",
    "name": "Notify CRM daily",
    "definition_status": "active",
    "next_run_at": "2026-07-19T12:00:00Z",
    "schedule": {
      "kind": "daily_at",
      "timezone": "America/Sao_Paulo",
      "time": "09:00"
    },
    "schedule_human": "Every day at 09:00"
  }
}
```

### 17.14 API Request Idempotency

Task creation and manual-run endpoints SHOULD accept:

```http
Idempotency-Key: <client-generated-key>
```

When the same authenticated client repeats a request with the same key and equivalent payload within the retention window, the API SHOULD return the original result rather than create a duplicate resource or run.

---

## 18. Frontend Requirements

### 18.1 Frontend Stack

The recommended MVP frontend is:

- Vue 3;
- TypeScript;
- Vite;
- Vue Router;
- Pinia;
- Tailwind CSS; and
- Vue I18n.

Production hosting MUST serve compiled static assets. Node.js is required only during development and build.

### 18.2 Required Screens

- Login
- Tenant and environment switcher
- Dashboard
- Task list
- Task detail
- New task wizard
- Endpoint profile list and detail
- Run history
- Run detail with attempts
- Environment management
- API key management
- Tenant members
- User preferences
- Tenant settings
- Platform health for super admins

### 18.3 Dashboard

The dashboard MUST show:

- active, paused, and failed task counts;
- recent runs;
- runs waiting for retry;
- dead runs requiring attention;
- next upcoming scheduled runs;
- cron heartbeat status;
- environment filter; and
- a prominent create-task action.

### 18.4 Task Creation Wizard

The recommended steps are:

1. Basics
2. Destination
3. Authentication
4. Request Data
5. Schedule
6. Retry Policy
7. Test
8. Review and Activate

Requirements:

- each step MUST remain focused and short;
- advanced settings MUST use progressive disclosure;
- defaults MUST be visible and editable;
- validation MUST occur inline;
- the schedule step MUST show the next three expected occurrences;
- secret values MUST never reappear after submission; and
- the final review MUST summarize destination, schedule, retry behavior, and security warnings.

### 18.5 Schedule Language

Examples:

- `One time on Jul 19, 2026 at 09:00`
- `Every 15 minutes`
- `Every weekday at 09:00`
- `Every Monday and Thursday at 14:30`
- `Monthly on day 5 at 08:00`

The frontend MUST not construct schedule descriptions through fragile sentence-fragment concatenation. It MUST use locale-aware templates.

### 18.6 Task List

The task list SHOULD support:

- search by name;
- filters by environment, status, latest run state, and schedule type;
- sorting by next run, latest run, and name;
- bulk pause and resume; and
- compact visibility of latest result and next occurrence.

### 18.7 Run Detail

A run detail screen MUST show:

- task and trigger type;
- scheduled time;
- run state;
- idempotency key, partially masked where appropriate;
- attempt timeline;
- status code or transport error;
- duration;
- retry decision;
- next retry time;
- redacted request snapshot;
- truncated response snapshot; and
- clear explanation of terminal failure.

---

## 19. Internationalization

### 19.1 Initial Locales

- English: `en`
- Brazilian Portuguese: `pt-BR`

### 19.2 Translation Scope

All user-facing frontend text MUST be translatable, including:

- navigation;
- labels;
- buttons;
- validation messages;
- status badges;
- empty states;
- onboarding text;
- dialog content;
- dashboard summaries;
- wizard steps;
- schedule descriptions;
- date and time formats; and
- mapped API errors.

### 19.3 Translation Architecture

Requirements:

- user-facing strings MUST use semantic translation keys;
- locale files MUST be version-controlled;
- English MUST be the fallback locale;
- the user MUST be able to select a locale explicitly;
- browser-language detection MAY be used only for the first visit;
- user preference MUST override tenant and browser defaults;
- tenant default MUST override the platform default; and
- the HTML `lang` attribute MUST update when the locale changes.

Recommended structure:

```text
frontend/src/i18n/
  index.ts
  locales/
    en/
      common.json
      auth.json
      dashboard.json
      tasks.json
      endpointProfiles.json
      environments.json
      runs.json
      settings.json
      validation.json
    pt-BR/
      common.json
      auth.json
      dashboard.json
      tasks.json
      endpointProfiles.json
      environments.json
      runs.json
      settings.json
      validation.json
```

### 19.4 Translation Rules

- Use stable semantic keys, for example `tasks.status.paused`.
- Do not use full English sentences as translation keys.
- Do not concatenate translated sentence fragments.
- Support interpolation and pluralization.
- Avoid HTML inside translation strings.
- Keep technical identifiers and stable API error codes untranslated.

### 19.5 Formatting

The frontend MUST localize:

- dates;
- times;
- weekdays;
- relative timestamps;
- timezones;
- durations; and
- numeric values.

Examples:

- `en`: `Jul 18, 2026, 7:30 AM`
- `pt-BR`: `18 de jul. de 2026, 07:30`

---

## 20. Accessibility

The frontend MUST target WCAG 2.1 AA practices and include:

- semantic HTML;
- full keyboard navigation;
- visible focus indicators;
- accessible labels and descriptions;
- screen-reader announcements for asynchronous status changes;
- sufficient color contrast;
- no status communicated only by color;
- accessible dialogs and menus;
- error summaries for complex forms; and
- locale-aware document language.

Automated accessibility checks do not replace keyboard and screen-reader smoke testing.

---

## 21. Observability and Diagnostics

### 21.1 User Diagnostics

Users MUST be able to determine:

- whether the task was due;
- whether a run was created;
- whether an attempt started;
- where the request was sent, with sensitive data redacted;
- what response or transport failure occurred;
- whether the failure is retryable;
- when the next retry will occur; and
- why a run became terminal.

### 21.2 Platform Diagnostics

The platform MUST record:

- last successful scheduler cycle;
- last successful retry cycle;
- last maintenance cycle;
- executor duration;
- number of claimed tasks;
- number of successful and failed attempts;
- stale claims recovered;
- cleanup results; and
- application version.

### 21.3 Health Endpoint

A protected platform health endpoint SHOULD report:

```json
{
  "status": "healthy",
  "database": "ok",
  "scheduler_last_seen_at": "2026-07-18T14:20:00Z",
  "retry_executor_last_seen_at": "2026-07-18T14:20:00Z",
  "stale_claims": 0,
  "version": "1.1.0"
}
```

The endpoint MUST NOT expose credentials, filesystem paths, private URLs, or tenant data.

### 21.4 Audit Events

Audit logging MUST include:

- login and failed-login security events;
- tenant creation and update;
- member invitation, removal, and role change;
- environment creation, archival, and restoration;
- API key creation and revocation;
- task creation, update, activation, pause, resume, archive, and manual run;
- endpoint profile changes;
- secret creation, rotation, and deletion;
- outbound policy changes; and
- platform support access to tenant data.

Audit records MUST store actor, tenant, action, resource, timestamp, request identifier, and a redacted change summary.

---

## 22. Data Retention and Cleanup

### 22.1 Default Retention

Recommended defaults:

- detailed request and response snapshots: 30 days;
- attempt metadata: 180 days;
- run summary metadata: 365 days;
- audit logs: 365 days;
- API idempotency records: 24 hours; and
- system heartbeat records: 30 days.

### 22.2 Cleanup Requirements

Maintenance MUST:

- delete or null expired payload snapshots;
- preserve summary metadata after payload removal;
- clear stale claims;
- prune expired API idempotency records;
- remove expired password-reset tokens;
- report cleanup counts; and
- operate in small batches to avoid long database locks.

Tenant retention settings MAY shorten retention within platform-defined limits. Increasing retention beyond platform limits is deferred.

---

## 23. Performance and Capacity

### 23.1 Intended Workload

The MVP targets low-to-moderate shared-hosting workloads, not high-throughput delivery.

A reference installation SHOULD support, subject to hosting limits:

- up to 100 tenants;
- up to 5,000 task definitions;
- up to 500 active tasks due within a typical hour; and
- up to 20 outbound requests per one-minute executor cycle by default.

These values are planning targets, not hard guarantees.

### 23.2 Query Requirements

- Due-task queries MUST use indexed `next_run_at` and state columns.
- Dashboard aggregates SHOULD use bounded queries or maintained summaries.
- Large run-history endpoints MUST paginate.
- Cleanup MUST be batched.
- The executor MUST avoid full-table schedule scans.

### 23.3 Backpressure

When more work is due than one cycle can process:

- unclaimed work MUST remain due for the next cycle;
- the UI SHOULD indicate scheduler backlog;
- the platform SHOULD expose the oldest pending due timestamp; and
- no task may bypass tenant or platform concurrency limits.

Per-tenant fairness is recommended for hardening but is not required for the first MVP milestone.

---

## 24. Deployment Requirements

### 24.1 Runtime

- PHP 8.2 or later
- MySQL 8 or a documented compatible MariaDB release
- Apache or LiteSpeed
- PHP CLI or a hosting mechanism capable of invoking the executor
- cron with one-minute cadence where available
- writable application storage outside the public web root

### 24.2 Production Dependencies

The production host MUST NOT require:

- Node.js;
- Docker;
- Redis;
- a process supervisor;
- a persistent queue worker; or
- shell access after installation, provided the hosting panel can run the required commands.

### 24.3 Recommended Backend

The preferred implementation is a modular Laravel application using:

- framework authentication and authorization primitives;
- a framework-supported HTTP client;
- database migrations;
- encrypted configuration primitives where appropriate;
- CLI commands for executors and maintenance; and
- a service-oriented application layer.

A different PHP framework MAY be used only if it provides equivalent security, migration, CLI, testing, and maintainability capabilities without increasing MVP risk.

### 24.4 Installation Flow

The project SHOULD provide:

1. dependency installation or a release package with vendor dependencies;
2. environment configuration;
3. application-key generation;
4. database migration;
5. first-admin bootstrap;
6. frontend asset deployment;
7. writable-directory verification;
8. cron command instructions;
9. scheduler heartbeat verification; and
10. a post-install security checklist.

### 24.5 Shared-Hosting Web Root

Only the application's `public` directory MUST be web-accessible. Configuration, source files, logs, encryption keys, and writable storage MUST remain outside the public document root whenever the hosting provider allows it.

### 24.6 Cron Configuration Example

```cron
* * * * * /usr/bin/php /home/account/app/artisan scheduler:execute-due >/dev/null 2>&1
* * * * * /usr/bin/php /home/account/app/artisan scheduler:retry-due >/dev/null 2>&1
17 * * * * /usr/bin/php /home/account/app/artisan scheduler:maintenance >/dev/null 2>&1
```

Paths are deployment-specific. The documentation MUST explain how to configure equivalent jobs through a hosting control panel.

---

## 25. Application Architecture

### 25.1 Architectural Style

The MVP MUST be a modular monolith.

Suggested modules:

```text
Auth
PlatformAdministration
Tenancy
Memberships
Environments
EndpointProfiles
Secrets
Tasks
Scheduling
Execution
Retries
TaskRuns
ApiKeys
Audit
Notifications
Localization
Health
Retention
```

### 25.2 Layering

Recommended boundaries:

```text
Interface Layer
  HTTP controllers
  CLI commands
  request validation

Application Layer
  use cases
  authorization orchestration
  transactions

Domain Layer
  schedule calculation
  state transitions
  retry decisions
  policies

Infrastructure Layer
  database persistence
  encryption
  HTTP transport
  mail
  framework adapters
```

### 25.3 Coding Rules

- Controllers MUST NOT contain core business logic.
- Schedule calculation MUST be isolated and testable without HTTP or database access.
- Retry decisions MUST be isolated and deterministic.
- Outbound request construction and delivery MUST be handled by a dedicated service.
- Tenant scoping MUST not depend on developers remembering to add filters manually.
- State transitions MUST be explicit.
- Framework models MUST NOT become the only representation of domain rules.
- User-facing frontend text MUST not be hard-coded in components.
- Secret redaction MUST be centralized.
- SSRF validation MUST be reusable by tests, task testing, and production execution.

### 25.4 Suggested Repository Structure

```text
project-root/
  app/
    Application/
    Domain/
    Infrastructure/
    Http/
    Console/
    Policies/
  bootstrap/
  config/
  database/
    factories/
    migrations/
    seeders/
  frontend/
    src/
      components/
      pages/
      router/
      stores/
      services/
      i18n/
      utils/
  public/
  resources/
  routes/
  storage/
  tests/
    Unit/
    Integration/
    Feature/
    EndToEnd/
  docs/
```

---

## 26. Testing Strategy

### 26.1 Unit Tests

Unit tests MUST cover:

- each schedule type;
- timezone conversion;
- daylight-saving transitions;
- monthly missing-day behavior;
- missed-occurrence policy;
- retry delay calculation;
- `Retry-After` handling;
- success and retry classification;
- run and attempt state transitions;
- URL and IP policy validation;
- secret redaction; and
- idempotency-key generation.

### 26.2 Integration Tests

Integration tests MUST cover:

- tenant isolation;
- environment isolation;
- API key scopes;
- task creation and activation;
- due-task claiming;
- overlapping executor processes;
- unique scheduled-run creation;
- stale-claim recovery;
- retry claiming;
- request and response persistence;
- retention cleanup; and
- audit logging.

### 26.3 Frontend Tests

Frontend tests MUST cover:

- locale switching;
- fallback translations;
- task wizard validation;
- schedule previews;
- secret-field behavior;
- task creation happy path;
- task pause and resume;
- run-history rendering; and
- accessibility smoke checks.

### 26.4 End-to-End Tests

The MVP end-to-end suite MUST verify that a user can:

1. log in;
2. select a tenant and environment;
3. create an endpoint profile;
4. create a scheduled task;
5. test the destination;
6. activate the task;
7. execute the cron command;
8. observe the outbound request on a test receiver;
9. inspect the run and attempt history;
10. observe a retry after a transient failure; and
11. pause and resume the task.

### 26.5 Security Tests

Security tests MUST include:

- cross-tenant identifier substitution;
- private-IP and localhost blocking;
- IPv6 loopback and private-range blocking;
- redirect to a blocked destination;
- DNS rebinding-resistant validation behavior where feasible;
- secret redaction in logs and API responses;
- unauthorized environment access;
- API key revocation; and
- malicious header rejection.

---

## 27. MVP Scope

### 27.1 Included

- platform-admin bootstrap;
- tenant creation by platform admins;
- tenant memberships and roles;
- multiple environments;
- endpoint profiles;
- encrypted secrets;
- inline endpoints;
- HTTP task definitions;
- human-friendly schedules;
- cron-driven execution;
- at-least-once delivery;
- retries;
- manual runs;
- endpoint tests;
- task and attempt history;
- API keys with scopes;
- basic failure notifications;
- audit logging;
- retention cleanup;
- English and Brazilian Portuguese; and
- shared-hosting installation documentation.

### 27.2 Deferred

- public self-registration;
- billing and usage metering;
- visual workflows;
- task dependencies;
- arbitrary code execution;
- multipart file uploads;
- custom cron-expression UI;
- per-tenant custom retention beyond platform caps;
- distributed workers;
- real-time streaming logs;
- marketplace integrations;
- payload templating beyond static structured bodies;
- per-tenant execution fairness controls; and
- exactly-once delivery claims.

---

## 28. MVP Acceptance Criteria

The MVP is complete only when all of the following are true:

### 28.1 User Workflow

A tenant user can:

- log in;
- switch between English and Brazilian Portuguese;
- select an environment;
- create an endpoint profile and secret;
- create a scheduled HTTP task through a wizard;
- test the endpoint;
- activate the task;
- trigger a manual run;
- have cron execute a scheduled run;
- inspect all attempts and redacted diagnostics;
- observe retry behavior;
- pause and resume the task; and
- archive the task.

### 28.2 Reliability

- Two overlapping cron processes do not create duplicate scheduled runs.
- An interrupted attempt is recoverable after claim expiry.
- A delayed cron cycle does not backfill every missed occurrence.
- Retries do not corrupt the next recurring schedule.
- Every logical run uses a stable idempotency key.

### 28.3 Security

- Cross-tenant access tests pass.
- Private and local network destinations are blocked by default.
- Redirect destinations are revalidated.
- Secrets are encrypted and redacted.
- API keys are hashed and shown only once.
- TLS verification is enabled by default.

### 28.4 Operability

- The scheduler heartbeat is visible.
- Failed and dead runs are discoverable from the dashboard.
- Cleanup jobs operate in bounded batches.
- Installation and cron setup are documented and reproducible.

### 28.5 Quality Gate

- Required automated tests pass.
- Database migrations work on a clean database.
- The application builds without requiring Node.js in production.
- No user-facing strings are hard-coded outside the i18n system.
- No critical or high-severity dependency vulnerability remains without a documented mitigation.

---

## 29. Delivery Roadmap

### Phase 0: Foundation

- repository and coding standards;
- Laravel application bootstrap;
- Vue and i18n bootstrap;
- authentication;
- tenant context;
- authorization policies;
- core migrations; and
- continuous integration.

### Phase 1: Core Configuration

- environments;
- secrets;
- endpoint profiles;
- tasks;
- schedule model;
- task wizard; and
- endpoint testing.

### Phase 2: Reliable Execution

- due-task claiming;
- HTTP execution;
- run and attempt records;
- retry engine;
- stale-claim recovery;
- idempotency headers; and
- scheduler heartbeat.

### Phase 3: Operability and Hardening

- dashboard;
- filters and run diagnostics;
- audit logs;
- failure email hooks;
- retention cleanup;
- security test suite;
- accessibility checks; and
- shared-hosting deployment guide.

### Phase 4: SaaS Readiness

- quotas;
- metering;
- plan limits;
- tenant provisioning automation;
- billing hooks;
- per-tenant concurrency controls;
- worker adapter; and
- managed-domain policies.

---

## 30. Migration Path Beyond Shared Hosting

The core domain and API MUST remain independent from cron-specific execution.

A future deployment may replace the cron executor with queue workers by implementing new claim and transport adapters while preserving:

- task definitions;
- schedule data;
- run and attempt models;
- retry policies;
- public API resources;
- idempotency semantics; and
- frontend workflows.

The migration path SHOULD allow mixed execution during transition, but a task MUST have only one active execution backend at a time.

---

## 31. Implementation Guidance for AI Coding Agents

### 31.1 Required Working Order

AI coding agents SHOULD implement in this sequence:

1. establish project conventions and automated tests;
2. implement authentication and tenant context;
3. implement tenant-isolated persistence;
4. implement schedule calculation as pure, tested services;
5. implement task, run, and attempt state machines;
6. implement SSRF validation and secret redaction;
7. implement executor claiming and interruption recovery;
8. implement outbound HTTP delivery and retries;
9. implement the REST API;
10. implement the frontend flows;
11. add observability, retention, and deployment documentation; and
12. complete the end-to-end acceptance suite.

### 31.2 Agent Constraints

Agents MUST NOT:

- weaken tenant scoping to simplify a query;
- execute user URLs before SSRF validation;
- store secrets in task JSON or logs;
- merge task definition state with run state;
- treat attempts as separate logical runs;
- run outbound HTTP calls inside long database transactions;
- introduce Redis or long-running workers into the MVP;
- expose raw cron syntax in the default UI;
- claim exactly-once execution;
- silently change the missed-occurrence policy; or
- add framework shortcuts that bypass authorization policies.

### 31.3 Change Discipline

For every implementation change, an agent SHOULD:

- identify the affected requirement section;
- add or update tests first when practical;
- document any deliberate deviation;
- update API examples when contracts change;
- update migrations and seed data consistently;
- verify tenant isolation; and
- run the relevant unit, integration, and frontend tests before declaring completion.

---

## 32. Resolved MVP Decisions

The following decisions are fixed for the MVP:

| Topic | Decision |
|---|---|
| Tenant creation | Platform-admin controlled; no public self-registration |
| Endpoint configuration | Both reusable endpoint profiles and inline endpoints |
| API key scope | Tenant scope required; optional environment restriction |
| Manual runs | Create a new run and do not modify the recurring schedule |
| Manual retry | Create another attempt in the same run |
| Payload templating | Deferred beyond static request bodies |
| Missed schedules | Skip backlog and create at most one due run per claim cycle |
| Delivery guarantee | At least once |
| Cron expressions | Not exposed in the MVP UI or public schedule contract |
| Backend framework | Laravel is preferred unless an equivalent alternative is justified |
| Frontend | Vue 3 with TypeScript, Vite, Tailwind, and Vue I18n |
| Production Node.js | Not required after assets are compiled |
| Task deletion | Archive by default when history exists |
| Private network delivery | Blocked by default |
| Retry relationship to schedule | Retries are independent from future recurring occurrences |

---

## 33. Deferred Design Questions

These questions do not block the MVP but should be revisited during hardening or SaaS planning:

- Should tenant administrators be allowed to request private-network destinations in managed deployments?
- Should the hosted edition support per-tenant execution regions?
- Should payload templating support variables from schedule, tenant, or previous responses?
- Should per-tenant fairness use round-robin claiming or weighted quotas?
- Should long-term run metadata be exported to object storage?
- Should webhook signatures be added as a first-class authentication mode?
- Should a future advanced schedule API support RRULE in addition to cron?

---

## 34. Final Recommendation

Build the MVP as a modular Laravel monolith with a Vue 3 frontend, a MySQL-backed schedule and execution state model, encrypted secret references, strict tenant isolation, and short-lived cron executors.

The implementation should optimize for predictable behavior on constrained shared hosting. It must be explicit about minute-level precision, at-least-once delivery, skipped historical occurrences, and the possibility of duplicate attempts. Reliability should come from durable database state, atomic claims, idempotency keys, bounded execution, stale-claim recovery, and comprehensive diagnostics—not from assumptions about perfect cron timing or clean process termination.
