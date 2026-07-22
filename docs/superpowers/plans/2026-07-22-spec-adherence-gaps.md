# v1 Extension Spec adherence gaps — plan

**Origin:** uploaded / repo copy `docs/superpowers/specs/2026-07-22-taskconnect-v1-extension-spec.md` (byte-identical)  
**Date:** 2026-07-22  
**Verdict:** **Not 100% adherent.** R1–R18 are largely shipped; remaining work is listed below.

## Summary table

| Area | Status | Notes |
|------|--------|-------|
| R1–R3, R5–R6, R9, R11, R13, R15–R18 | Done | Tests + architecture docs on `main` |
| R4 | Done | Caps/weight/priority + **`timeout_ms` applied on delivery** (#62) |
| R7 | Done | SSRF/DNS-pin + per-host rate limit + optional robots (#63) |
| R8 | Done (opt-in) | Flags default off — accepted dual-mode / Q1 |
| R10 | Done | Pipeline DAG; chaining not on bare task create (#66) |
| R12 / R17 | Done | RR + WFQ; claim-time preemption only |
| R14 | Done (UI) | Missing authenticated Playwright depth (#65) |
| §8 S8/S10 | Partial | Submit limits yes; claim/delivery audit + callback verify limit (#64) |
| §6.1 literal schema | Done | Mapping doc `docs/architecture/api-contract-v1.md` (#66) |

## Implementation order

1. **#62** `timeout_ms` → delivery timeouts — **done**
2. **#63** egress extras — **done**
3. **#64** audit + S8 clarification/limiter
4. **#65** Playwright authenticated operator journeys
5. **#66** contract mapping doc — **done**

## Non-goals for this epic

- Breaking rename of `url_or_path` → `target_url` without a versioned migration
- Mid-flight HTTP cancel for “preemption”
- Implementing GrandpaSSOn `tasks:write` vocabulary inside this repo (tracked at [grandpasson#55](https://github.com/suporterfid/grandpasson/issues/55))

## Verification

For each child: unit/feature tests green via `./scripts/tc.sh test` (and Playwright when #65); update `docs/architecture/*` when behavior changes; keep hard constraints (no Redis/daemon).
