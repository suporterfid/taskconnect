# Backup and Recovery

Back up regularly:

1. MySQL database dump
2. `.env` (including `APP_KEY`) stored encrypted offline
3. Application files if customized outside release packages

## Restore

1. Restore files and `.env`
2. Restore database
3. Clear caches: `php artisan config:clear && php artisan cache:clear`
4. Confirm cron and heartbeats

Without `APP_KEY`, encrypted secrets cannot be decrypted.
