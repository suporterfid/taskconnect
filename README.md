🇧🇷 [Leia em Português](README.pt-BR.md) | 🇺🇸 [English](README.md) (this page)

# TaskConnect

> Multi-tenant HTTP task scheduler and webhook dispatcher engineered for commodity PHP/MySQL shared hosting.

> 🍵 **Part of the IROH Initiative** (*It Runs On Hostinger*)  
> This project is open-source, self-hosted software built for traditional shared hosting.  
> Learn more at the main hub: [suporterfid/iroh](https://github.com/suporterfid/iroh) | [Manifesto](https://github.com/suporterfid/iroh/blob/main/MANIFESTO.md) | [Projects Index](https://github.com/suporterfid/iroh/blob/main/PROJECTS.md) | [Branding Spec](https://github.com/suporterfid/iroh/blob/main/BRANDING.md)

---

## Overview

**TaskConnect** is a self-hosted, lightweight multi-tenant HTTP task scheduler and automated webhook delivery engine designed to run efficiently on traditional shared hosting platforms (Hostinger, cPanel, Apache/Nginx) with zero requirement for dedicated background daemons or expensive cloud infrastructure. Built with PHP 8.2+ (Laravel 12 backend architecture) and Vue 3 (SPA operator interface), TaskConnect delivers reliable background job execution, recurring cron scheduling, and HTTP callbacks on budget hosting plans equipped only with PHP, MySQL, and a standard per-minute cron job.

As a core project within the **IROH Initiative** ("It Runs On Hostinger"), TaskConnect exemplifies the principles of self-sovereignty, resource minimalism, and high-performance engineering tailored for accessible web hosting environments.

---

## Part of the IROH Initiative

TaskConnect is developed under the **IROH Initiative**, a collection of self-hosted open-source software crafted to empower developers and small teams to host full-featured applications on standard cPanel / shared hosting accounts.

* **Central Hub**: [https://github.com/suporterfid/iroh](https://github.com/suporterfid/iroh)
* **Manifesto**: [Read the IROH Manifesto](https://github.com/suporterfid/iroh/blob/main/MANIFESTO.md)
* **Project Index**: [Explore all IROH Projects](https://github.com/suporterfid/iroh/blob/main/PROJECTS.md)
* **Branding Specification**: [Visual Identity & Design System](https://github.com/suporterfid/iroh/blob/main/BRANDING.md)

---

## Features

- **Multi-Tenant HTTP Task Scheduling**: Define one-shot delayed webhooks, recurring cron schedules, or manual executions scoped across isolated tenant environments.
- **Daemonless MySQL Claim Leasing**: High-concurrency claiming engine (`scheduler:execute-due` / `scheduler:retry-due`) utilizing MySQL transaction locking (`lockForUpdate`) with configurable TTL lease recovery.
- **Hardened Outbound SSRF Protection**: Outbound DNS-pinned HTTP transport (`OutboundPolicy`) preventing DNS rebinding, private IP range scanning, cloud metadata access, and unauthorized internal network calls.
- **Dead Letter Queue (DLQ) & Alerting**: Comprehensive terminal failure management, payload inspection, manual replay via CLI/UI, and automated email/webhook notifications per environment.
- **Pipeline DAG Execution**: Chain tasks into execution graphs (`on_success`, `on_failure`, `depends_on`) with burst-coalescing and weighted round-robin workspace fairness.
- **Encrypted Secrets & Payload Redaction**: Transparent symmetric encryption at rest for endpoint credentials, with automatic redaction in execution logs and audit snapshots.
- **Modern Vue 3 SPA Operator UI**: Content-first, responsive management panel built with Vue 3, Vite, Pinia, and Tailwind CSS.
- **Single Sign-On (GrandpaSSOn)**: Native integration with `suporterfid/grandpasson-ce` for OAuth2/OIDC authentication, signed JWT tokens, and HMAC callback signature verification.

---

## Requirements

- **Production Target (Shared Hosting)**:
  - PHP 8.2+ with PDO extensions (`pdo_sqlite`, `pdo_mysql`)
  - MySQL 8.0+ or MariaDB 10.5+
  - Apache or LiteSpeed with `mod_rewrite` enabled (Document Root set to `public/`)
  - Standard per-minute cron capability
- **Dual Database Architecture**:
  - **SQLite**: Supported for single-tenant local development, testing, and micro-deployments.
  - **MySQL / MariaDB**: Recommended for multi-tenant production deployments on shared hosting.
- **100% Docker-Containerized Local Developer Experience**:
  - Full local stack featuring `web` (Nginx), `php` (PHP-FPM), `frontend` (Node 20 / Vite dev server), and `db` (MySQL 8.0).
  - Every codebase is 100% testable, buildable, and runnable locally within Docker Compose containers without requiring local host installations of PHP or Node.
- **Cross-Platform CLI Parity**:
  - Symmetrical CLI workflow via PowerShell (`.\scripts\tc.ps1`) and Bash (`./scripts/tc.sh`) for container management, running test suites, compiling frontend assets, running migrations, and creating shared hosting release packages.

---

## Frontend & UI Design System

TaskConnect's frontend interface strictly adheres to the IROH Notion-inspired, content-first visual identity specification:
- **Semantic CSS Token Contract**: Driven by standard CSS custom properties (`:root`, `[data-theme='light']`, `[data-theme='dark']`) ensuring accessible theme switching and zero build-time theme pollution.
- **Accessibility & Responsiveness**: Fully WCAG 2.2 AA compliant contrast ratios, visible focus indicators (`:focus-visible`), and a responsive mobile-first shell layout.
- **Branding Reference**: See the initiative [Branding & Visual Identity Spec](https://github.com/suporterfid/iroh/blob/main/BRANDING.md).

---

## Installation

### Shared Hosting Deployment (cPanel / Hostinger)

1. **Download / Build Release**:
   Download the latest release ZIP package from the releases page, or build it locally using:
   ```bash
   # Windows PowerShell
   .\scripts\tc.ps1 release

   # Linux / macOS
   ./scripts/tc.sh release
   ```
   This generates a production-ready ZIP package under the `dist/` directory.

2. **Upload & Extract**:
   Upload the ZIP archive to your hosting account and extract its contents into your target root folder (e.g., `/public_html/taskconnect` or dedicated subdomain root).

3. **Document Root Configuration**:
   Configure your hosting domain or subdomain to point its **Document Root** to the `public/` subdirectory.

4. **Environment Setup**:
   Copy `.env.example` to `.env` and set your production environment credentials:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://taskconnect.yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_db_name
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```

5. **Database Migration & Admin Bootstrap**:
   Run database migrations and create your initial administrator account via CLI (SSH) or using the deployment helper:
   ```bash
   php artisan migrate --force
   php artisan platform:bootstrap-admin admin@yourdomain.com "SecurePassword123!" --name="Admin"
   ```

6. **Cron Configuration**:
   Add the following entries to your shared hosting Cron Jobs manager (set to execute every minute):
   ```cron
   * * * * * /usr/bin/php /path/to/taskconnect/artisan scheduler:execute-due >/dev/null 2>&1
   * * * * * /usr/bin/php /path/to/taskconnect/artisan scheduler:retry-due >/dev/null 2>&1
   17 * * * * /usr/bin/php /path/to/taskconnect/artisan scheduler:maintenance >/dev/null 2>&1
   ```

---

## Quickstart & Local DX (Docker)

All local operations are containerized. Do not install PHP or Node directly on your host machine.

### Windows (PowerShell)
```powershell
Copy-Item .env.example .env
.\scripts\tc.ps1 up
.\scripts\tc.ps1 bootstrap
.\scripts\tc.ps1 artisan platform:bootstrap-admin admin@example.com "ChangeMeNow!" --name="Admin"
```

### Linux / macOS (Bash)
```bash
cp .env.example .env
./scripts/tc.sh up
./scripts/tc.sh bootstrap
./scripts/tc.sh artisan platform:bootstrap-admin admin@example.com "ChangeMeNow!" --name="Admin"
```

* **Application SPA**: http://localhost:8080
* **Mailpit (Email Sandbox)**: http://localhost:8025
* **Receiver (HTTP Test Target)**: http://localhost:8090

### CLI Command Summary

| Command | Purpose |
| :--- | :--- |
| `tc up` / `tc down` | Start or stop Docker local environment containers |
| `tc test` | Execute full backend PHPUnit test suite inside container |
| `tc composer ...` | Execute Composer commands inside PHP container |
| `tc artisan ...` | Execute Artisan commands inside PHP container |
| `tc npm ...` | Execute npm commands inside Node container |
| `tc release` | Package production shared-hosting ZIP file under `dist/` |

---

## Authentication & Single Sign-On (SSO)

TaskConnect supports native single sign-on integration with [GrandpaSSOn](https://github.com/suporterfid/grandpasson-ce) (`suporterfid/grandpasson-ce`), the official central IdentityProvider for the IROH ecosystem.

- **OIDC / OAuth2 Support**: Connect TaskConnect to GrandpaSSOn to authenticate users via centralized identity management.
- **Signed JWT & Client Credentials**: Outbound webhook delivery supports client-credentials authentication and signed JWT headers.
- **HMAC Callback Verification**: Verifies webhook responses using HMAC signatures and custom headers (`X-TC-Task-Id`, `X-TC-Workspace`).

---

## Integration with Other IROH Projects

TaskConnect seamlessly integrates with companion tools in the IROH Initiative ecosystem:

* 🔐 [suporterfid/grandpasson-ce](https://github.com/suporterfid/grandpasson-ce) — Central Single Sign-On (SSO) IdentityProvider and OAuth2 server.
* 📝 [suporterfid/jotter](https://github.com/suporterfid/jotter) — Self-hosted Markdown knowledge base with WebDAV sync.
* 📄 [suporterfid/ConvertDocToMd-PHP](https://github.com/suporterfid/ConvertDocToMd-PHP) — Plain PHP DOC/DOCX/PDF to Markdown conversion utility.
* ⚡ [suporterfid/taskconnect](https://github.com/suporterfid/taskconnect) — Multi-tenant HTTP task scheduler & webhook dispatcher (this project).
* 📊 [suporterfid/statusconnect](https://github.com/suporterfid/statusconnect) — Uptime monitoring & public service status page.
* 🔢 [suporterfid/tallymark](https://github.com/suporterfid/tallymark) — Minimalist tally counter & event logging microservice.

---

## Contributing

We welcome community contributions! Please review our [Contributing Guidelines](CONTRIBUTING.md) or check the centralized initiative governance at the [IROH Meta-repo Contributing Guide](https://github.com/suporterfid/iroh/blob/main/CONTRIBUTING.md).

---

## License

TaskConnect is open-source software licensed under the [MIT License](LICENSE).
