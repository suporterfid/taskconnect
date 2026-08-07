Residual final re-review fixes are ready for independent verification; issue #132 remains open intentionally.

- `PageHeader` now isolates all titles with production `<bdi dir="auto">`, covering Run Detail and Pipeline Detail machine IDs without special-casing localized headings.
- API-key environment fallback IDs and key prefixes now use `BidiText` at readonly/table render sites.
- Centralized units now include English/Brazilian Portuguese pluralized seconds and milliseconds.
- Endpoint-profile timeout values use localized seconds; Run Detail uses locale-aware attempt counts and localized attempt durations, with no raw `s`/`ms` suffix concatenation at those sites.
- Deterministic en/pt formatting tests and a source inventory prevent regressions.

Residual evidence:

- focused RED: 2 failed / 19 passed; focused GREEN: 3 files / 21 tests;
- full frontend: 29 files / 216 tests passed;
- build: 1,940 modules transformed;
- affected authenticated browser proof: 1/1 passed, including the seeded Run Detail ID in `h1 > bdi[dir="auto"]` after all four exact-200%-text widths;
- backend unchanged; the immediately preceding fresh 239 tests / 843 assertions remain authoritative;
- release checksum and secret scan passed;
- ZIP: 9,293,915 bytes;
- SHA256: `d23ab4fa4bfc028736b92d8ee5436aebd53dcf92d23308492e66dde6a76833d9`;
- forbidden application artifacts, raw duration suffixes, and direct/default locale-formatting hits: 0;
- `git diff --check`: pass.
