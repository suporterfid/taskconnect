# Cron Configuration

```cron
* * * * * /usr/bin/php /home/account/app/artisan scheduler:execute-due >/dev/null 2>&1
* * * * * /usr/bin/php /home/account/app/artisan scheduler:retry-due >/dev/null 2>&1
17 * * * * /usr/bin/php /home/account/app/artisan scheduler:maintenance >/dev/null 2>&1
```

Replace paths with your hosting account paths. Equivalent jobs can be created in cPanel / hPanel cron UIs.

## Verification

After the first minute, platform health should show recent `scheduler_last_seen_at` and `retry_executor_last_seen_at` timestamps. The dashboard displays a stale-cron warning when heartbeats are older than a few minutes.
