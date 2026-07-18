# Production Requirements

- PHP 8.2 or later with extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo_mysql, session, tokenizer, xml, zip, intl, bcmath
- MySQL 8.0+ (or documented MariaDB equivalent)
- Apache or LiteSpeed; document root must point at the application `public/` directory
- Cron with one-minute cadence preferred
- Writable directories: `storage/`, `bootstrap/cache/`
- Outbound HTTPS access from the host for task delivery

Not required in production: Node.js, Docker, Redis, queue workers, Composer (when using a release package that includes `vendor/`).
