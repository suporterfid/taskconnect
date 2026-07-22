# Spec §12 open questions — defaults applied (#35)

Canonical defaults from the v1 Extension spec. Product overrides should update this table and GitHub [#35](https://github.com/suporterfid/taskconnect/issues/35) (closed after audit).

| # | Default | Status | Where enforced |
|---|---------|--------|----------------|
| **Q1** | Add `tasks:write` to GrandpaSSOn; dual-mode inbound during migration | Applied | `config/grandpasson.php` (`write_scope`, inbound/outbound flags default **off**); inbound introspection in `AuthenticateApiKeyOrSanctum` / policies; broker follow-up [grandpasson#55](https://github.com/suporterfid/grandpasson/issues/55) (closes TaskConnect [#26](https://github.com/suporterfid/taskconnect/issues/26)) |
| **Q2** | Formalize delayed `run_at`; recurring = structured kinds (+ optional cron) | Applied | `docs/architecture/delayed-schedules.md`; top-level `run_at` → once schedule; `ScheduleKind::Cron` |
| **Q3** | Prefer notes-app crawl; ship `public-crawl` profile | Applied | Profile in `config/outbound.php` / egress resolver; only `site.crawl` type defaults to it — not used by convert/reminder/publish (`docs/architecture/egress-profiles.md`) |
| **Q4** | Global 4; convert 2; crawl 1; index 2; reminder 4 | Applied | `config/task_types.php` + `.env.example` `TASK_TYPE_*` |
| **Q5** | Introspect inbound; cache outbound client-credentials | Applied | Inbound: introspection client per request; outbound: `CachedTokenClient` + `GRANDPASSON_TOKEN_REFRESH_SKEW_SECONDS` |
| **Q6** | DLQ 30d; enqueue idempotency 24h | Applied | `RETENTION_DEAD_RUNS_DAYS=30`; `IDEMPOTENCY_ENQUEUE_TTL_HOURS=24` |

No remaining `TODO(spec): Qn` markers required for these defaults.
