# Fairness scheduling (R12)

`scheduler:execute-due` / `retry-due` claim work **globally**, but candidates are interleaved with **weighted round-robin across workspaces** (`environment_id`) so one workspace’s backlog cannot monopolize a tick.

## Algorithm

1. Select due / pending / retry candidates ordered by existing priority rules (over-fetch).
2. Group by `environment_id`, preserving within-workspace order.
3. Interleave: each workspace gets `SCHEDULER_FAIRNESS_WORKSPACE_WEIGHT` picks per round (default **1** = classic round-robin).
4. Apply R4 per-type + global capacity checks while walking the interleaved list.

Within a single workspace, higher `priority` / earlier `next_run_at` still win. Across workspaces, fairness binds starvation: a saturated workspace A cannot fill the entire batch before workspace B’s due light work is considered.

## Config

```
SCHEDULER_FAIRNESS_WORKSPACE_WEIGHT=1
```

P2 may refine this toward WFQ / preemption (R17); this release is RR with optional weight.
