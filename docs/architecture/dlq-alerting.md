# DLQ alerting (R13)

When a run settles as `dead`, TaskConnect can notify operators via email and/or a workspace webhook.

## Channels

| Channel | Defaults | Disable |
|---------|----------|---------|
| Email | On (`dead_run_email_enabled=true`); still gated by `SCHEDULER_FAILURE_EMAILS_ENABLED` and per-user `failure_emails_enabled` | Workspace PATCH or global env |
| Webhook | Off until enabled + URL set | Workspace PATCH or `SCHEDULER_FAILURE_WEBHOOKS_ENABLED=false` |

## Workspace settings API

```
PATCH /api/v1/tenants/{tenant}/environments/{env}
{
  "notifications": {
    "dead_run_email_enabled": true,
    "dead_run_webhook_enabled": true,
    "dead_run_webhook_url": "https://hooks.example.com/tc-dlq"
  }
}
```

Webhook URLs are validated with the `public-crawl` egress profile (SSRF / private IP blocked) before save. Alert posts use the DNS-pinned transport with short timeouts and do not follow redirects.

## Audit

- `dlq.alert.email_sent` — email channel fired (`recipient_count`)
- `dlq.alert.webhook_sent` / `dlq.alert.webhook_failed` — webhook outcome (`webhook_host`, `status_code`; full URL never logged)

Alert failures never roll back the dead-run settlement.
