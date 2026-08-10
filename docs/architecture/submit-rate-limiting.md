

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


Task and pipeline **submission** endpoints are rate-limited with a **MySQL/SQLite fixed window** (`rate_limit_buckets`). No Redis.

## Scoped endpoints

- `POST â€¦/tasks`
- `POST â€¦/tasks/{id}/run-now`
- `POST â€¦/tasks/{id}/test`
- `POST â€¦/pipelines/{template}/instances`

Key: `submit:{tenant_id}:{environment_id}` (workspace-scoped).

## Config

| Setting | Default | Notes |
|---------|---------|--------|
| `SCHEDULER_SUBMIT_RATE_LIMIT_PER_MINUTE` | 60 | Global default |
| `SCHEDULER_SUBMIT_RATE_LIMIT_WINDOW_SECONDS` | 60 | Window length |
| `environments.submit_rate_limit_per_minute` | null | Optional per-workspace override via `PATCH â€¦/environments/{id}` |

## 429 response

Exceeded windows return HTTP **429** with `Retry-After` (seconds until window reset) and API envelope `error.code = too_many_requests`.
