# Platform metrics (R18)

Prometheus-style text exposition for operators and scrapers. No sidecar, no always-on agent — scraped over HTTP from shared hosting like any other API route.

## Endpoint

```
GET /api/v1/platform/metrics
```

- Auth: Sanctum/API key as usual, then **platform admin** only (same gate as `/platform/health`).
- Content-Type: `text/plain; version=0.0.4; charset=utf-8`
- Cache-Control: `no-store`

## Series

| Metric | Type | Meaning |
|--------|------|---------|
| `taskconnect_queue_depth{state=…}` | gauge | `pending` / `retry_wait` / `blocked` run counts |
| `taskconnect_inflight` | gauge | `running` runs |
| `taskconnect_inflight_by_type{task_type=…}` | gauge | running runs by task type |
| `taskconnect_dlq_size` | gauge | `dead` runs |
| `taskconnect_attempt_duration_ms{task_type,quantile}` | summary | p50 / p95 from up to 500 recent attempts with `duration_ms` |
| `taskconnect_attempt_duration_ms_count{task_type}` | summary count | sample size per type |
| `taskconnect_scheduler_tick_duration_seconds{command}` | gauge | last tick duration from heartbeat meta (`execute_due` / `retry_due`) |
| `taskconnect_scheduler_configured_budget_seconds` | gauge | configured `SCHEDULER_TARGET_DURATION_SECONDS` |
| `taskconnect_scheduler_tick_budget_seconds{command}` | gauge | budget applied on last tick |
| `taskconnect_scheduler_budget_stopped{command}` | gauge | `1` if last tick exited early due to budget |

Latencies are approximate (bounded recent sample) so the endpoint stays cheap on MySQL shared plans.

## Example scrape

```bash
curl -H "Authorization: Bearer …" https://example.com/api/v1/platform/metrics
```
