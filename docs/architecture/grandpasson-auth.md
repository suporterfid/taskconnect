# GrandpaSSOn delegated auth (R8)

Outbound callbacks (when `GRANDPASSON_OUTBOUND_ENABLED=true`):

```
Authorization: Bearer <client-credentials token, scope tasks:callback>
Idempotency-Key: <stable per run>
X-TC-Task-Id / X-TC-Workspace / X-TC-Timestamp / X-TC-Nonce
X-TC-Signature: HMAC-SHA256(TC_CALLBACK_HMAC_SECRET, timestamp + "." + nonce + "." + raw_body)
```

Verify with constant-time compare (`hash_equals`); reject skew beyond `TC_CALLBACK_MAX_SKEW_SECONDS`.

Inbound (when `GRANDPASSON_INBOUND_ENABLED=true`): opaque bearer tokens are introspected; SPA Sanctum and `tc_*` API keys remain valid (dual-mode). Machine tokens must include scope `tasks:write` and `aud` covering the target workspace public id — mismatches return **403** and audit `grandpasson.workspace_denied`.

Requires GrandpaSSOn to expose `tasks:write` / `tasks:callback`. TaskConnect-side R8 is done; broker vocabulary follow-up is [grandpasson#55](https://github.com/suporterfid/grandpasson/issues/55) (see `docs/architecture/grandpasson-cross-repo.md`). Defaults keep both flags **false**.

`aud` may be the raw Environment public id or `workspace/<id>`.
