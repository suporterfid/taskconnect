# Final fix report — Notion-inspired identity final review

Date: 2026-08-07

Baseline: `3f62d5b`

Tracking issue: [#132](https://github.com/suporterfid/taskconnect/issues/132)

## Outcome

This final whole-branch fix wave resolves all seven Important findings from the independent review while preserving the framework-agnostic, semantic-token, i18n-first identity contract and shared-hosting release boundary.

1. User-controlled values now use the production `BidiText` primitive (`<bdi dir="auto">`), the signed-in identity exposes the complete email address, and back/flow affordances use selectively mirrored Lucide icons.
2. Dates, numbers, sorting, and pluralized duration units use centralized locale-aware `Intl` helpers rather than ambient/default locale behavior.
3. Text inputs and textareas preserve IME composition and emit the completed value only after `compositionend`.
4. The app shell implements exact `<480`, `480–767`, `768–1023`, `1024–1279`, and `>=1280` behavior, including narrow top-bar overflow, overlay navigation below 1024, and persistent collapsible navigation from 1024 upward.
5. Current navigation uses `--color-bg-selected`, independently from hover styling.
6. The browser proof now covers exact 200% text, Chromium 200%/400% page scale, authenticated and anonymous widths, pseudo-locales, real script/bidi input, RTL navigation, focus, clipping, and strict 44 px targets.
7. App shell, menu/tooltip, toast, and dialog surfaces use the declared semantic layer tokens instead of numeric z-index values.

The responsive browser matrix exposed one real defect: at 480 px with exact 200% text, the sticky header obscured the page action. The header is now in document flow through 767 px. A final source audit also found one pipeline-sequence arrow glyph; a RED guard was added and the glyph was replaced with a directional `ArrowRight` icon plus bidi-isolated node values.

## TDD evidence

- Initial focused RED: missing locale-format module, two IME failures, three app-shell contract failures, and six bidi/directional-page failures.
- Initial focused GREEN: 4 files / 36 tests.
- Layer/layout focused GREEN after browser-discovered fixes: 2 files / 23 tests.
- Pipeline-flow RED: `identity.components.spec.ts` had 1 failed / 14 passed because the literal right arrow bypassed RTL mirroring.
- Pipeline-flow GREEN: 1 file / 15 tests.
- The first build after that change correctly rejected unsupported `AppIcon` size 14 (`TS2322`); the icon now uses the supported 16 px size and the subsequent production build passed.

### Residual re-review addendum

- Residual focused RED: 3 files / 21 tests, with 2 failures covering unisolated page-header titles and the missing bidi/unit source inventory.
- Residual focused GREEN: **3 files / 21 tests passed**.
- `PageHeader` now renders every title through `<bdi dir="auto">`; API-key environment fallbacks and key prefixes use the same production primitive.
- `second` and `millisecond` extend the centralized unit contract and have English/Brazilian Portuguese plural messages. Endpoint-profile timeouts and run-attempt durations no longer concatenate raw `s`/`ms`; run attempt counts use `Intl.NumberFormat` through `formatNumber`.
- The affected authenticated browser proof initially exposed an existing fixture mismatch: the helper described an active task but created a draft and omitted the required run-now idempotency key, leaving `runId` null. The E2E-only fixture now creates an active task with a unique idempotency key. The rerun passed and asserted the seeded Run Detail machine ID in `h1 > bdi[dir="auto"]` after the four-width exact-200%-text matrix.

## Final automated gates

All project commands ran through `scripts/tc.ps1` and Docker Compose.

- Frontend: **29 files / 216 tests passed**.
- Backend: **239 tests / 843 assertions passed**.
- Production build: `vue-tsc -b && vite build` passed; **1,940 modules transformed**.
- Identity browser suite: **17/17 Chromium tests passed** in 1.5 minutes.
- Affected residual browser proof: **1/1 Chromium test passed** in 25.1 seconds.
- Release: checksum validation passed and the validator reported `secret-scan=pass`.

Expected test-only stderr is limited to the simulated locale-persistence network failure and the isolated login-page router warning for `/forgot-password`.

## Browser evidence

- Chromium 400% page scale used a 1280 px layout viewport and a 320 px visual viewport; document `scrollWidth` equaled `clientWidth`, and all six audited controls were at least 44×44 px, focusable, visible, and unobscured.
- Separate 200% page scale passed.
- Anonymous exact 200% text passed at 480, 768, 1024, and 1280 px with exact 2.0 font-size and line-height ratios, no page overflow/clipped text, and compliant controls.
- Authenticated Task Detail exact 200% text passed at all four widths. At 480/768, 38 adjusted elements and 13 controls passed; at 1024/1280, 37 adjusted elements and 29 controls passed. Font and line-height ratios were exactly 2.0.
- Pseudo-localization exercises every visible text node: short interactive copy expands to 2× and general copy to at least 1.3× in `en-XA`; `ar-XB` and real Arabic, Hebrew, CJK, Thai, Devanagari, Cyrillic, and Greek content cover bidi and wrapping behavior.
- Authenticated checks cover shipped `<bdi dir="auto">` values, directional action icons, selected-token rendering, semantic layer computed values, narrow top-bar overflow, and sidebar behavior at the specified breakpoints.
- Light/dark authenticated and anonymous pages pass axe checks; forced-colors and reduced-motion behaviors pass.
- The strict target audit permits only documented inline-data/table exceptions; representative application controls pass the 44×44 px contract.

The local browser environment used host port 8082 because another workspace owned 8080. Browser containers use origin `http://app`, so `APP_URL=http://app` was required for asset URLs. The first isolated attempt with a localhost asset origin was an environment/harness RED and was corrected before authoritative browser evidence was recorded.

## Authoritative release audit

- ZIP: `dist/taskconnect-release.zip`
- Size: **9,293,915 bytes**
- SHA256: `d23ab4fa4bfc028736b92d8ee5436aebd53dcf92d23308492e66dde6a76833d9`
- Recorded checksum: exact match
- Forbidden application source/test/output artifact count: **0**
- Secret scan: **pass**

The release contains the compiled semantic CSS, theme bootstrap, Vite manifest, Inter variable font and license, English and Brazilian Portuguese server locales, Laravel error shell, and production vendor tree. It excludes the frontend source tree, application tests, `.superpowers`, Playwright/test outputs, screenshots, and Node dependencies.

## Static audits

- `git diff --check`: pass
- Raw color declarations in product Vue files: **0**
- Literal directional arrow glyphs in product Vue/CSS: **0**
- Numeric z-index declarations/classes in product Vue/CSS: **0**
- Direct page/component `Intl` construction, `localeCompare`, or `toLocale*` fallback: **0**
- Raw `s`/`ms` duration suffix concatenations in product Vue templates: **0**
- Physical left/right CSS or directional spacing utilities: **0**
- Release forbidden application artifact count: **0**

## Tracking and remaining decision

Issue #132 remains open intentionally. This wave is ready for independent re-review; issue closure belongs to that review, not this implementation pass. No known product blocker remains in the requested scope.
