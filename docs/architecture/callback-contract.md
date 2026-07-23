# Outbound callback contract (receiver)

TaskConnect delivers HTTP callbacks to task destinations. Receivers SHOULD treat deliveries as **at-least-once** and dedupe on the stable run key.

## Idempotency (v1 Extension R3)

Every outbound delivery includes:

| Header | Value | Notes |
|--------|--------|------|
| `Idempotency-Key` | Run’s stable `idempotency_key` | **Canonical** — constant across retries of the same run |
| `X-Task-Idempotency-Key` | Same value | **Deprecated compatibility alias** — prefer `Idempotency-Key` |
| `X-Task-Run-Id` | Run public id | Diagnostic |
| `X-Task-Attempt` | Attempt number (1-based) | Diagnostic; changes per retry |
| `X-TC-Task-Id` | Task public id | v1 Extension §6.3 — always sent, independent of GrandpaSSOn outbound auth |
| `X-TC-Workspace` | Environment public id | v1 Extension §6.3 — always sent, independent of GrandpaSSOn outbound auth |

### Receiver rules

1. Dedupe side effects on `Idempotency-Key` (fall back to `X-Task-Idempotency-Key` during migration).
2. Automatic retries and manual retry-from-dead **reuse** the same delivery key for that run.
3. DLQ **replay** (`tasks:dlq:replay`, R6) mints a new delivery idempotency group — treat as a new logical delivery.

### Example

```http
POST /internal/convert HTTP/1.1
Idempotency-Key: run:42:2026-07-22T12:00:00Z
X-Task-Idempotency-Key: run:42:2026-07-22T12:00:00Z
X-Task-Run-Id: run_01ABC…
X-Task-Attempt: 2
X-TC-Task-Id: tsk_01ABC…
X-TC-Workspace: env_01ABC…
```

Attempt `2` carries the **same** `Idempotency-Key` as attempt `1` for that run.

> GrandpaSSOn bearer + `X-TC-Signature` HMAC arrive in R8; this document covers idempotency headers only.
>
> **S8:** HMAC / freshness / nonce verification is the **receiver’s** duty (notes app). TaskConnect does not expose an inbound verify endpoint — see `docs/architecture/audit-s8-s10.md`.
