# Fairness scheduling (R12 + R17)

`scheduler:execute-due` / `retry-due` claim work **globally**, but candidates are interleaved across workspaces (`environment_id`) so one workspace’s backlog cannot monopolize a tick.

## Algorithms

1. Select due / pending / retry candidates ordered by existing priority rules (over-fetch).
2. Group by `environment_id`, preserving within-workspace order.
3. Interleave with the configured mode (below).
4. Apply R4 per-type + global capacity checks while walking the interleaved list.

### `rr` (R12)

Weighted round-robin: each workspace gets `SCHEDULER_FAIRNESS_WORKSPACE_WEIGHT` picks per round (default **1**). Each pick costs **1**.

### `wfq` (R17, default)

Deficit round-robin: each visit adds the same quantum, but each pick costs `max(1, task.weight)`. Heavy tasks must accumulate deficit before claiming, so light workspaces keep getting turns.

### Claim-time priority preemption (R17)

When `SCHEDULER_PRIORITY_PREEMPTION_MIN` is set, up to `SCHEDULER_PRIORITY_PREEMPTION_SLOTS` tasks with `priority >= min` are selected first (still fairness-interleaved among themselves), then the remainder runs through normal RR/WFQ.

**Not** mid-flight preemption: in-flight HTTP deliveries are never cancelled (shared-hosting / cron constraint).

## Config

```
SCHEDULER_FAIRNESS_WORKSPACE_WEIGHT=1
SCHEDULER_FAIRNESS_MODE=wfq
SCHEDULER_PRIORITY_PREEMPTION_MIN=
SCHEDULER_PRIORITY_PREEMPTION_SLOTS=1
```

Within a single workspace, higher `priority` / earlier `next_run_at` still win in the candidate query. Across workspaces, fairness + optional preemption bound starvation.
