# Upgrades

1. Put the site in maintenance mode if available: `php artisan down`
2. Back up database and `.env`
3. Replace application files with the new release (preserve `.env` and `storage/`)
4. Run `php artisan migrate --force`
5. Clear caches: `php artisan optimize:clear`
6. `php artisan up`
7. Confirm heartbeats and a manual test run

Release packages include `vendor/` and compiled `public/build` assets; production hosts do not need Composer or Node.
