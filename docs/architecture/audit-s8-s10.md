# Audit (S10) and rate limits (S8)

## S10 — Workspace-scoped audit

`audit_logs` records operator and system security-relevant actions. Scheduler path (cron) now also emits:

| Action | When | Summary (redacted metadata) |
|--------|------|------------------------------|
| `scheduler.claim` | After a due / pending / retry claim lease succeeds | `source`, `attempt_number`, `task_id`, `task_type`, `trigger_type` |
| `scheduler.delivery` | After a delivery settles | `outcome` (`succeeded` \| `retry_wait` \| `dead` \| `blocked`), `http_status`, `error_code`, `task_id` |
| `dlq.replayed` | DLQ replay | existing |
| `grandpasson.workspace_denied` | Inbound GSS aud/scope fail | existing |
| enqueue / CRUD | Task create, etc. | existing controller audits |

Bodies, headers, and secrets are **not** written to audit summaries (see `AuditLogger::redact`).

Toggles (default on): `SCHEDULER_AUDIT_CLAIMS`, `SCHEDULER_AUDIT_DELIVERIES`.

## S8 — Rate limiting & callback verification

| Surface | Owner | Mechanism |
|---------|-------|-----------|
| Task/pipeline **submission** | TaskConnect | DB `rate_limit_buckets` (R15) — `docs/architecture/submit-rate-limiting.md` |
| Egress **per-host** (public-crawl/api) | TaskConnect | DB buckets — `docs/architecture/egress-profiles.md` |
| **Callback verification** (HMAC + freshness + nonce non-replay) | **Notes-app / receiver** | Not a TaskConnect HTTP endpoint. TC **mints** `X-TC-*` HMAC headers when GrandpaSSOn outbound is enabled; the **receiver** verifies. No TC inbound “verify callback” API → no TC verify rate limiter. |
| Inbound GSS token introspect | TaskConnect | Dual-mode middleware; failures audited. Submission still covered by R15 when creating work. |

If a future shared verify helper or inbound status webhook is added to this repo, it **must** use `DatabaseRateLimiter` (no Redis).
