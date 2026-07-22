# Coalesce / debounce (R11)

Bursty enqueues that share a `coalesce_key` within a **workspace** collapse to the first effective task for a configurable window.

## Behavior

- Optional `coalesce_key` on `POST …/tasks` (and on pipeline node delivery configs).
- Lookup is scoped by `tenant_id` + `environment_id` (workspace) + `coalesce_key`.
- Window: `SCHEDULER_COALESCE_WINDOW_SECONDS` (default **60**), measured from the first task's `created_at`.
- Hit → **200** with the existing task and `meta.coalesced: true` (no second row).
- Miss → **201** create with `meta.coalesced: false`.

Distinct from R2 `Idempotency-Key`: idempotency replays the exact same request; coalesce merges **different** submits that share a semantic key (e.g. many edits → one `publish.build`).

## Pipelines (R10)

When materializing a `publish.build` node, TaskConnect defaults `coalesce_key` to `pipeline:{template}:publish.build` unless the node payload sets one explicitly. Multiple pipeline instances that try to materialize publish within the window reuse one task.

## Config

```
SCHEDULER_COALESCE_WINDOW_SECONDS=60
```
