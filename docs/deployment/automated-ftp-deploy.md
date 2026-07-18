# Automated Deployment (FTP + SSH)

`tc deploy` builds the production release and publishes it to shared hosting in one
command. It uses a throwaway Docker image (`lftp` + `ssh` + `sshpass` + `jq`), so
you do **not** need any FTP or SSH client installed on your machine — the
Docker-only rule still holds. It works identically from Windows (PowerShell) and
Linux/macOS (bash).

## What it does

1. Builds the production release tree into `dist/app` (Composer `--no-dev`,
   compiled frontend assets, dev tooling stripped).
2. Injects a hardened root `.htaccess` that routes all traffic into `public/` and
   blocks direct access to `.env` / source directories — so the whole app can
   safely live inside `public_html`.
3. **When SSH is enabled (recommended):** packages the tree into a single zip,
   uploads it over SFTP, extracts on the server, preserves `storage/` and `.env`,
   then runs `optimize:clear`, `migrate --force`, `storage:link`, and
   `config:cache` / `route:cache`.
4. **Fallback (SSH disabled):** mirrors `dist/app` over FTPS with `lftp`.

## One-time setup

The real credentials live in `deploy.config.json`, which is **git-ignored**. Copy
the example and fill it in:

```bash
cp deploy.config.example.json deploy.config.json
```

Config reference:

| Key | Meaning |
| --- | --- |
| `ftp.secure` | `true` = FTPS (explicit TLS over port 21, Hostinger default). |
| `ftp.verify_certificate` | `false` when connecting by raw IP (cert won't match). |
| `ftp.remote_dir` | Absolute path to `public_html`. |
| `options.delete_remote` | `false` (safe). `true` removes remote files not present locally. |
| `options.inject_root_htaccess` | Keep `true` for the single-folder `public_html` layout. |
| `options.upload_env` | `true` uploads `options.env_source` to the remote `.env`. Off by default. |
| `ssh.php_binary` | Absolute path to PHP ≥ 8.2. On Hostinger this is typically `/opt/alt/php83/usr/bin/php` (the default `php` is often 7.4). |
| `ssh.run_migrations` | Runs `php artisan migrate --force` after upload. |

Also set the **website PHP version to 8.2+** in hPanel → Advanced → PHP Configuration, otherwise the web app will still run under 7.4 even though CLI migrations use 8.3.

## Deploy

```powershell
# Windows
.\scripts\tc.ps1 deploy
```

```bash
# Linux / macOS
./scripts/tc.sh deploy      # or: make deploy
```

## First deployment checklist

The remote `.env` is **never overwritten** by default, so before the first deploy
create it once on the server (via hPanel File Manager or SSH) with production
values — `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, and the
Hostinger MySQL credentials. See [installation.md](installation.md).

Then:

1. Run `tc deploy` — files upload and migrations run automatically over SSH.
2. Bootstrap the first admin over SSH:
   ```bash
   ssh -p 65002 u250556264@62.72.52.123
   cd domains/hub.taskconnect.com.br/public_html
   php artisan platform:bootstrap-admin you@example.com 'StrongPassword' --name='You'
   ```
3. Add the scheduler cron (see [cron.md](cron.md)).
4. Visit `https://hub.taskconnect.com.br` and confirm the health endpoint.

## Security note

The FTP and SSH passwords were shared in plaintext. `deploy.config.json` keeps
them out of git, but you should **rotate both passwords** in hPanel now that they
have been transmitted, then update `deploy.config.json`.
