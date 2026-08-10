# Status

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*

---

## Current Status: Production Ready (v1.3.0)

TaskConnect is actively maintained and deployed as a production-grade multi-tenant HTTP task scheduler for shared hosting environments.

### Core Capabilities Verified

- **Daemonless MySQL Claim Leasing**: Fully operational via `scheduler:execute-due` and `scheduler:retry-due` artisan commands.
- **SSRF Hardening**: DNS-pinned HTTP transport (`OutboundPolicy`) prevents unauthorized private network traversal and DNS rebinding attacks.
- **Dead Letter Queue (DLQ)**: Automatic capture of terminal execution failures with CLI/UI replay commands and email/webhook alert notifications.
- **Pipeline DAG Execution**: Support for chained task pipelines (`on_success`, `on_failure`, `depends_on`) with workspace weight fairness.
- **Single Sign-On**: Full authentication integration with `suporterfid/grandpasson` OIDC/OAuth2 services.
- **Operator SPA Interface**: Accessible Vue 3 management interface with responsive shell and i18n support (`en`, `pt-BR`).
- **Shared Hosting Deployment**: Standardized `.htaccess`, `public/` docroot, and single ZIP build workflow (`tc release`).

---

## Test Suite & Verification Status

- **Backend Unit & Feature Tests**: Passed via PHPUnit (`tc test`).
- **Frontend Unit Tests**: Passed via Vitest (`tc npm --prefix frontend run test`).
- **Frontend Type Checks**: Passed via `vue-tsc` (`tc npm --prefix frontend run build`).
- **End-to-End Tests**: Automated Playwright smoke suite (`frontend/e2e/`).
