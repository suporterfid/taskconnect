# Troubleshooting

| Symptom | Checks |
|---------|--------|
| Login CSRF / 419 | `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, HTTPS/`APP_URL` alignment |
| Tasks never run | Cron paths, PHP CLI path, `scheduler:execute-due` manually, heartbeats |
| Duplicate concern | Unique `(task_id, occurrence_key)` / claim tokens; overlapping cron is expected to be safe |
| SSRF blocked | Destination private/local; allowlist is testing-only |
| Secrets unreadable | `APP_KEY` mismatch after restore |
| Assets 404 | Ensure `public/build` from release is deployed |
| Playwright browsers missing | In the node container: `npx playwright install --with-deps chromium`. Slim images may need apt packages; e2e is optional for merge |
| Need end-to-end acceptance without browsers | Follow `docs/deployment/acceptance-checklist.md` |

Logs: `storage/logs/laravel.log`. Mail capture in development: Mailpit at port 8025.
