# Cross-repo GrandpaSSOn scopes (#26)

TaskConnect R8 is **complete** in this repo (dual-mode inbound/outbound; flags default off). Remaining broker vocabulary work lives in GrandpaSSOn.

| Scope | TaskConnect use | Status |
|-------|-----------------|--------|
| `tasks:callback` | Outbound callback bearer | Specified in GrandpaSSOn extension §6.3; issuable when granted on the service client |
| `tasks:write` | Inbound submission introspection | Tracked as [grandpasson#55](https://github.com/suporterfid/grandpasson/issues/55) |

## Audience

TaskConnect compares introspected `aud` to the Environment public id (`workspace_id`). Accepted forms:

- raw public id: `env_…`
- prefixed: `workspace/env_…` (GrandpaSSOn doc style)

See `IntrospectionResult::audienceIncludes` and `docs/architecture/grandpasson-auth.md`.

## Operator enablement

1. In GrandpaSSOn: create a service client with `--scopes=tasks:callback,tasks:write` and `--aud=<workspace_id>`.
2. In TaskConnect: set `GRANDPASSON_*` URLs/credentials and flip `GRANDPASSON_OUTBOUND_ENABLED` / `GRANDPASSON_INBOUND_ENABLED` when ready.
