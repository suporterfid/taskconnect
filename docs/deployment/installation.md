# Installation (Shared Hosting)

1. Upload the release archive (from `tc release` / CI) and extract outside the public web root when possible.
2. Point the site document root to `public/`.
3. Copy `.env.example` to `.env` and set:
   - `APP_URL`
   - `APP_KEY` (or run `php artisan key:generate` once)
   - Database credentials
   - Mail settings
4. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP user.
5. Run `php artisan migrate --force`.
6. Bootstrap the first platform admin:
   ```bash
   php artisan platform:bootstrap-admin you@example.com 'StrongPassword' --name='You'
   ```
7. Configure cron (see [cron.md](cron.md)).
8. Log in, create a tenant, verify the platform health endpoint and scheduler heartbeat after one minute.
9. Complete the [security checklist](security.md).

## Retention (platform defaults)

Cleanup is driven by minute/hourly cron (`scheduler:maintenance`) using env vars from `.env.example` / `config/retention.php`:

| Variable | Default | Purpose |
|----------|---------|---------|
| `RETENTION_PAYLOAD_SNAPSHOTS_DAYS` | 30 | Redacted request/response bodies |
| `RETENTION_ATTEMPT_METADATA_DAYS` | 180 | Attempt rows metadata |
| `RETENTION_RUN_SUMMARY_DAYS` | 365 | Run summaries |
| `RETENTION_AUDIT_LOGS_DAYS` | 365 | Audit log rows |
| `RETENTION_API_IDEMPOTENCY_HOURS` | 24 | Idempotency keys |
| `RETENTION_SYSTEM_HEARTBEAT_DAYS` | 30 | Old heartbeat rows |
| `RETENTION_DEAD_RUNS_DAYS` | 30 | Dead-letter (`run_state=dead`) runs (R6 / Q6) |

Operators can view the effective values in **Settings → Retention** (read-only) or Platform Health (admins).

## Application key backup

Secrets are encrypted with `APP_KEY`. Losing the key makes stored secrets unrecoverable. Back up `.env` securely offline.
