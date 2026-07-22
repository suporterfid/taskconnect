# Cron Configuration

```cron
* * * * * /usr/bin/php /home/account/app/artisan scheduler:execute-due >/dev/null 2>&1
* * * * * /usr/bin/php /home/account/app/artisan scheduler:retry-due >/dev/null 2>&1
17 * * * * /usr/bin/php /home/account/app/artisan scheduler:maintenance >/dev/null 2>&1
```

Replace paths with your hosting account paths. Equivalent jobs can be created in cPanel / hPanel cron UIs.

## Verification

After the first minute, platform health should show recent `scheduler_last_seen_at` and `retry_executor_last_seen_at` timestamps. The dashboard displays a stale-cron warning when heartbeats are older than a few minutes.

## Wall-clock budget (R5)

Each `scheduler:execute-due` / `scheduler:retry-due` tick stops **claiming** new work when elapsed wall time reaches the configured budget (`SCHEDULER_TARGET_DURATION_SECONDS`, default 45), also capped by PHP `max_execution_time - SCHEDULER_BUDGET_SAFETY_MARGIN_SECONDS` when `max_execution_time` is set. Work is claimed in small chunks (`SCHEDULER_CLAIM_CHUNK`) and executed immediately so a budget stop does not strand a large lease batch. Remaining due work is eligible on the next tick (leases + `SKIP LOCKED` keep overlapping ticks safe).

Some shared hosts throttle or delay cron below a true once-per-minute floor. Prefer raising `SCHEDULER_CLAIM_BATCH` / chunk size rather than assuming exact 60s spacing; heartbeats and the stale-cron warning remain the source of truth for missed ticks.
