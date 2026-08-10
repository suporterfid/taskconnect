

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


**Origin:** uploaded / repo copy `docs/superpowers/specs/2026-07-22-taskconnect-v1-extension-spec.md` (byte-identical)  
**Date:** 2026-07-22  
**Verdict:** **Not 100% adherent.** R1â€“R18 are largely shipped; remaining work is listed below.

## Summary table

| Area | Status | Notes |
|------|--------|-------|
| R1â€“R3, R5â€“R6, R9, R11, R13, R15â€“R18 | Done | Tests + architecture docs on `main` |
| R4 | Done | Caps/weight/priority + **`timeout_ms` applied on delivery** (#62) |
| R7 | Done | SSRF/DNS-pin + per-host rate limit + optional robots (#63) |
| R8 | Done (opt-in) | Flags default off â€” accepted dual-mode / Q1 |
| R10 | Done | Pipeline DAG; chaining not on bare task create (#66) |
| R12 / R17 | Done | RR + WFQ; claim-time preemption only |
| R14 | Done | UI + authenticated Playwright DLQ/pipelines (#65) |
| Â§8 S8/S10 | Done | Claim/delivery audit + S8 docs (callback verify = receiver) (#64) |
| Â§6.1 literal schema | Done | Mapping doc `docs/architecture/api-contract-v1.md` (#66) |

## Implementation order

1. **#62** `timeout_ms` â†’ delivery timeouts â€” **done**
2. **#63** egress extras â€” **done**
3. **#64** audit + S8 clarification â€” **done**
4. **#65** Playwright authenticated operator journeys â€” **done**
5. **#66** contract mapping doc â€” **done**

## Non-goals for this epic

- Breaking rename of `url_or_path` â†’ `target_url` without a versioned migration
- Mid-flight HTTP cancel for â€œpreemptionâ€
- Implementing GrandpaSSOn `tasks:write` vocabulary inside this repo (tracked at [grandpasson#55](https://github.com/suporterfid/grandpasson/issues/55))

## Verification

For each child: unit/feature tests green via `./scripts/tc.sh test` (and Playwright when #65); update `docs/architecture/*` when behavior changes; keep hard constraints (no Redis/daemon).

## Closeout

Children **#62â€“#66** are implemented on `main`. Epic **#67** can close once #65 lands.
