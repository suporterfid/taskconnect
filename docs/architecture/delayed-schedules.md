

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


TaskConnect already had structured schedule kinds (`once`, `daily_at`, â€¦). R16 formalizes delayed reminders and optional five-field cron without requiring a queue broker.

## Top-level `run_at`

`POST/PATCH â€¦/tasks` accept either:

- `schedule` â€” structured config (existing), or
- `run_at` â€” ISO-8601 instant for a one-shot delayed run (optional `timezone`, default `UTC`).

`run_at` synthesizes `schedule.kind = once` with `at = run_at`. When both are present, `schedule` wins.

Task responses expose:

- `run_at` â€” the once-schedule `at` when kind is `once`, else `null`
- `schedule` / `schedule_human` â€” unchanged shapes

## Cron kind

`schedule.kind = cron` with `cron_expression` (exactly five fields: minute hour day-of-month month day-of-week). Evaluated in the schedule `timezone` via `dragonmantank/cron-expression` (already a Laravel transitive dependency).

Example:

```json
{
  "kind": "cron",
  "timezone": "America/Sao_Paulo",
  "cron_expression": "0 9 * * 1-5"
}
```

## Constraints

- Shared-hosting safe: next occurrence is still persisted as `tasks.next_run_at` and claimed by minute cron.
- Default SPA prefers structured kinds; cron is available for advanced operators (filter + wizard).
- Recurring reminders should keep using structured kinds; use `run_at` / `once` for delayed one-shots.
