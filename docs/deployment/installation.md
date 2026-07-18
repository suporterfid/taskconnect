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

## Application key backup

Secrets are encrypted with `APP_KEY`. Losing the key makes stored secrets unrecoverable. Back up `.env` securely offline.
