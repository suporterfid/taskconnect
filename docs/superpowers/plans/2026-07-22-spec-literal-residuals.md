

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


**Date:** 2026-07-22  
**Source:** uploaded `taskconnect-spec-v1-extension_75b5.md` (cmp: differs from repo only by absence of the #66 API-mapping bullet)  
**Epic:** [#76](https://github.com/suporterfid/taskconnect/issues/76)

## Verdict

**Not 100% literally adherent** to the attached Extension Spec.

| Layer | Status |
|-------|--------|
| MoSCoW **R1â€“R18** | Functionally **done** (prior epics; #67 closed) |
| Security **S1â€“S10** | Functionally **done** with documented dual-mode / receiver-side verify |
| Â§6.1 / Â§6.3 **literal** shapes | **Partial** â€” v0-compatible aliases; mapping doc exists |
| Â§11 Playwright depth | **Partial** â€” specs exist; authenticated paths skip without fixtures |

## Accepted deviations (out of scope for this epic)

See epic #76 body and `docs/architecture/api-contract-v1.md`. Do not break v0 clients or force GSS defaults on without a cutover plan.

## Implementation units

### 1. [#77](https://github.com/suporterfid/taskconnect/issues/77) â€” Always-on Â§6.3 identity headers

**Decision:** Emit `X-TC-Task-Id` + `X-TC-Workspace` from `HttpDeliveryService` (or shared header builder) on every delivery. Keep bearer + `X-TC-Signature` / timestamp / nonce behind `GRANDPASSON_OUTBOUND_ENABLED`.

**Touch:** `app/Application/Execution/HttpDeliveryService.php`, `CallbackAuthHeaderBuilder.php` (avoid duplicate headers), `docs/architecture/callback-contract.md`, `tests/Unit/Execution/HttpDeliveryServiceTest.php`.

**Tests:** GSS outbound off â†’ still has `X-TC-Task-Id` + `X-TC-Workspace` + `Idempotency-Key`.

### 2. [#78](https://github.com/suporterfid/taskconnect/issues/78) â€” Opt-in Â§6.1 aliases

**Decision:** Additive mirrors only (`target_url` â† `url_or_path`; optional `payload` â† `body`). Gate with query/header flag; default responses unchanged.

**Touch:** `TaskResource` (and create/update docs), `docs/architecture/api-contract-v1.md`, feature test under `tests/Feature/Phase1/` or Tasks.

**Tests:** Default JSON unchanged; alias mode includes mirrors.

### 3. [#79](https://github.com/suporterfid/taskconnect/issues/79) â€” E2E auto-seed

**Decision:** When `E2E_*` set, seed dead run + pipeline instance via API (or local-only seed) so `dlq-pipelines.spec.ts` does not skip deep steps.

**Touch:** `frontend/e2e/`, optional artisan/API seed helper, `docs/deployment/e2e-operator.md`.

**Tests:** Documented `tc e2e` with credentials runs inspect/replay + pipeline detail (browsers installed).

## Sequencing

1. #77 (small, delivery contract)  
2. #78 (API additive)  
3. #79 (E2E / ops; depends on stable UI testids already present)

## Non-goals

- Mid-flight HTTP cancel for R17  
- Flat `/api/tasks` rewrite  
- Enabling GSS flags by default without broker cutover  
- Implementing GrandpaSSOn `tasks:write` inside this repo (grandpasson#55)
