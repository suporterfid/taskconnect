# Contributing Guidelines

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*

---

Thank you for your interest in contributing to **TaskConnect**!

## Initiative-Wide Governance

TaskConnect is part of the **IROH Initiative** ("It Runs On Hostinger"). Ecosystem-wide governance, architecture guidelines, branding standards, and contribution policies are managed centrally at:
👉 **[suporterfid/iroh - CONTRIBUTING.md](https://github.com/suporterfid/iroh/blob/main/CONTRIBUTING.md)**

## Development Workflow

1. **Docker-Only Local DX**:
   - All development, testing, asset compilation, and database migrations must be performed inside Docker containers using the CLI scripts:
     - PowerShell (Windows): `.\scripts\tc.ps1`
     - Bash (Linux / macOS): `./scripts/tc.sh`
   - Do not install PHP or Node.js on your local host machine.

2. **Testing & Code Quality**:
   - Run backend tests: `tc test`
   - Run frontend unit tests: `tc npm --prefix frontend run test`
   - Run frontend build/type check: `tc npm --prefix frontend run build`

3. **Submitting Changes**:
   - Track all non-trivial work on GitHub issues.
   - Use [Conventional Commits](https://www.conventionalcommits.org/) for git commit messages.
   - Ensure all new documentation files adhere to IROH Markdown standards and include the central hub header notice.
