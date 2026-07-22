# Manual MVP acceptance checklist (§26.4 / §28.1)

Use this when Playwright browsers are unavailable (slim node image) or for release smoke on shared hosting.

1. Log in (Sanctum SPA session).
2. Select tenant + environment.
3. Create an endpoint profile pointing at the `receiver` container (`http://receiver:8080/...`) or an allowlisted public URL.
4. Create a secret if the profile needs auth.
5. Create a task via the wizard (schedule + retry + optional sync test).
6. Activate the task.
7. Trigger **Run now** and/or wait for the next schedule minute.
8. Run cron executors:
   ```bash
   bash ./scripts/tc.sh artisan scheduler:execute-due
   bash ./scripts/tc.sh artisan scheduler:retry-due
   ```
9. Confirm the outbound request on the receiver (or target).
10. Inspect run + attempts (redacted snapshots).
11. Force a transient failure (receiver 5xx) and confirm retry wait / next attempt.
12. Pause and resume the task.
13. Switch UI locale EN ↔ pt-BR and confirm strings update.

Optional automated smoke (after `npx playwright install --with-deps chromium` in the node container):

```bash
bash ./scripts/tc.sh e2e
# Authenticated DLQ inspect/replay + pipeline detail:
# see docs/deployment/e2e-operator.md
# E2E_EMAIL=… E2E_PASSWORD=… bash ./scripts/tc.sh e2e
```
