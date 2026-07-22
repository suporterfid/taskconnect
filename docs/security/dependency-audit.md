# Dependency audit notes

TaskConnect targets shared hosting without required Redis/brokers. Dependency audits are part of the §28.5 quality gate.

## How to run

```bash
bash ./scripts/tc.sh composer audit
bash ./scripts/tc.sh npm --prefix frontend audit
```

Optional production-focused frontend pass:

```bash
bash ./scripts/tc.sh npm --prefix frontend audit --omit=dev
```

## Current status (2026-07-22)

Automated `composer audit` / `npm audit` from this agent environment could not reach Packagist/npm registries (network timeout). Treat the commands above as the source of truth on a networked workstation before release.

## Mitigation policy

| Severity | Policy |
|----------|--------|
| Critical / High | Must be fixed, upgraded, or documented here with owner + residual risk before tagging a release |
| Moderate | Prefer upgrade; document if deferred |
| Low / Informational | Track; no release block |

## Known accepted risks

- Playwright remains a **devDependency** for optional e2e; browsers are not required for production builds or shared-hosting deploy.
- `axe-core` is a **devDependency** used by Vitest a11y smoke only.

When an audit reports findings, append dated rows under this section with package, CVE/advisory id, mitigation, and follow-up.
