# Secret hygiene (R9)

## At rest / snapshots

- Tenant secrets use `SecretService` encryption (`encrypted_payload`); API never returns plaintext.
- Request/response snapshots pass through `SecretRedactor` / `RequestSnapshotRedactor`.

## Release zip scan

`scripts/validate-release.sh` (invoked by `tc release`) fails the build if the release tree or zip contains:

- `.env` / `.env.*` (except documentation placeholders outside the zip)
- Private key files / PEM `BEGIN … PRIVATE KEY` blocks
- Obvious third-party credential assignments (`AWS_SECRET_ACCESS_KEY`, `sk_live_…`, etc.)

Feature coverage: `tests/Feature/ReleaseSecretScanTest.php`.
