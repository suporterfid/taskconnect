# Submission rate limiting (R15)

Task and pipeline **submission** endpoints are rate-limited with a **MySQL/SQLite fixed window** (`rate_limit_buckets`). No Redis.

## Scoped endpoints

- `POST …/tasks`
- `POST …/tasks/{id}/run-now`
- `POST …/tasks/{id}/test`
- `POST …/pipelines/{template}/instances`

Key: `submit:{tenant_id}:{environment_id}` (workspace-scoped).

## Config

| Setting | Default | Notes |
|---------|---------|--------|
| `SCHEDULER_SUBMIT_RATE_LIMIT_PER_MINUTE` | 60 | Global default |
| `SCHEDULER_SUBMIT_RATE_LIMIT_WINDOW_SECONDS` | 60 | Window length |
| `environments.submit_rate_limit_per_minute` | null | Optional per-workspace override via `PATCH …/environments/{id}` |

## 429 response

Exceeded windows return HTTP **429** with `Retry-After` (seconds until window reset) and API envelope `error.code = too_many_requests`.
