# Egress profiles (R7)

Named profiles select SSRF / allowlist policy **before** DNS-pinned connect.

| Profile | Selection | Allows | Denies |
|---------|-----------|--------|--------|
| `internal` (default) | Most task types | Platform + tenant `outbound_allow_hosts` + local `testing_allow_hosts` | Non-allowlisted hosts; literal IPs |
| `public-crawl` | e.g. `site.crawl` type | Public unicast hostnames/IPs | RFC1918 / loopback / link-local / metadata / IPv6 ULA (no allowlist bypass except testing hosts) |
| `api` | e.g. `kb.index` type | `OUTBOUND_API_ALLOW_HOSTS` (+ testing allow hosts) | Everything else; private ranges |

Task `egress_profile` (or type default from `config/task_types.php`) is applied in `HttpDeliveryService` and re-checked on redirects in `GuzzlePinnedHttpTransport`.

Q3: `public-crawl` is shipped; only types that need public fetch (e.g. crawl) reference it by default.

## §6.5 extras

| Extra | Profiles | Behavior |
|-------|----------|----------|
| Max redirects / response-size / timeouts | all (profile overrides) | `config/outbound.php` `profiles.*` |
| Per-host rate limit | `public-crawl`, `api` | DB fixed window (`rate_limit_buckets` key `egress:{profile}:{tenant_id}:{host}`). Exceeded deliveries return synthetic **429** + `Retry-After` (retryable). Disable with limit `0`. |
| Optional `robots.txt` | `public-crawl` only | `OUTBOUND_PUBLIC_CRAWL_ROBOTS_TXT` (default **false**). Fetches `/robots.txt` via the pinned transport, caches the body, fail-open on fetch errors; `robots_disallow` blocks the attempt when the path is denied. |
