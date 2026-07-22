# TaskConnect

Open-source multi-tenant HTTP task scheduler for PHP/MySQL shared hosting.

**Current release:** [1.2.0](CHANGELOG.md)

## Requirements

### Production (shared hosting)

- PHP 8.2+
- MySQL 8.0+
- Apache or LiteSpeed with `mod_rewrite` (document root = `public/`)
- Cron capable of running every minute

### Development (required)

- Docker Desktop with Compose V2
- **Do not** install PHP, Composer, Node, or npm on the host

## Quick start (Docker)

```powershell
# Windows
Copy-Item .env.example .env
.\scripts\tc.ps1 up
.\scripts\tc.ps1 bootstrap
.\scripts\tc.ps1 artisan platform:bootstrap-admin admin@example.com "ChangeMeNow!" --name="Admin"
```

```bash
# Linux / macOS
cp .env.example .env
./scripts/tc.sh up
./scripts/tc.sh bootstrap
./scripts/tc.sh artisan platform:bootstrap-admin admin@example.com 'ChangeMeNow!' --name=Admin
```

App: http://localhost:8080  
Mailpit: http://localhost:8025  
Receiver: http://localhost:8090

## Common commands

| Verb | Purpose |
|------|---------|
| `tc up` / `tc down` | Start/stop core services |
| `tc composer …` | Composer inside PHP container |
| `tc artisan …` | Artisan inside PHP container |
| `tc npm …` | npm inside Node container |
| `tc test` | Backend test suite |
| `tc e2e` | Playwright E2E (when configured) |
| `tc release` | Build shared-hosting zip under `dist/` |

If Packagist is unreachable from your network, set an ephemeral mirror for the session only:

```powershell
$env:COMPOSER_PACKAGIST_URL = "https://mirrors.cloud.tencent.com/composer/"
.\scripts\tc.ps1 composer install
```

Never commit mirror configuration.

## Cron (production)

```cron
* * * * * /usr/bin/php /path/to/app/artisan scheduler:execute-due >/dev/null 2>&1
* * * * * /usr/bin/php /path/to/app/artisan scheduler:retry-due >/dev/null 2>&1
17 * * * * /usr/bin/php /path/to/app/artisan scheduler:maintenance >/dev/null 2>&1
```

See [docs/deployment/](docs/deployment/) for full installation, upgrade, security, and backup guidance.

## Architecture

Modular Laravel 12 monolith + Vue 3 SPA (`frontend/`), MySQL-backed claiming, at-least-once HTTP delivery with SSRF protection and encrypted secrets.

Product specification: [docs/http-task-scheduler-spec.md](docs/http-task-scheduler-spec.md)
