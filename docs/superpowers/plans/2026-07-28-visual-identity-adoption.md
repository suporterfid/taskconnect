# Visual identity adoption

**Date:** 2026-07-28
**Source:** uploaded *Generic Visual Identity Specification* → committed verbatim as `docs/visual-identity-spec.md`
**Epic:** [#85](https://github.com/suporterfid/taskconnect/issues/85)

## Verdict

The specification is **not applied anywhere today**. This is greenfield adoption, not a gap-closing pass.

| Spec area | Status |
|---|---|
| §3 color system / §11 tokens | **Absent** — no `@theme`, no custom properties, no `tailwind.config.*` |
| §2 dark by default | **Inverted** — light-first with `dark:` variants |
| §3.2 semantic roles | **Absent** — ~1,200 raw palette utilities across 26 `.vue` files |
| §4 typography | **Absent** — Open Sans never loaded; no type scale |
| §7 interface patterns | **Absent** — no primitives; one button class string duplicated ~31× |
| §8 marks and icons | **Wrong / none** — third-party template favicon and social logos; no in-app icons |
| §9 motion | **Absent** — no tokens, no `prefers-reduced-motion` |
| §10 accessibility | **Partial** — axe runs on one page with `color-contrast` **disabled** |

## Constraints carried into every unit

1. **Shared hosting** (CLAUDE.md invariant): no CDN font or icon dependency; everything self-hosted and bundled into `public/build`.
2. Tailwind v4 `@theme` is the token mechanism — no CSS-in-JS layer, no component library.
3. en + pt-BR both ship; longer pt-BR strings are part of every acceptance check.
4. No GitHub Actions CI — verification is `tc test`, `tc npm --prefix frontend run test`, `run build`, and `run e2e` locally.

## Implementation units

### Foundation

| Issue | Decision |
|---|---|
| [#86](https://github.com/suporterfid/taskconnect/issues/86) | Palette + semantic + radius + motion tokens in `frontend/src/style.css` `@theme`, mirrored into `resources/css/app.css`. Spacing maps onto Tailwind's existing 4px scale rather than duplicate `--space-*` tokens (§14.5). Ships a lint guard (Vitest source scan) with a shrinking allowlist. |
| [#87](https://github.com/suporterfid/taskconnect/issues/87) | Self-hosted Open Sans woff2 at 400/500/600/700 + SIL OFL record. Full §4.2 display scale on marketing/auth; a documented in-app step-down for `AppLayout`-hosted pages. |
| [#88](https://github.com/suporterfid/taskconnect/issues/88) | Dark-only (drop `dark:`), `color-scheme: dark` + black `html` in both shells to kill the flash, `AppLayout` migrated, skip link added. |
| [#96](https://github.com/suporterfid/taskconnect/issues/96) | One icon family, self-hosted, 16/20/24px, `currentColor`. **Pulled early** — #89 and #90 need icons to satisfy "never color alone". |
| [#89](https://github.com/suporterfid/taskconnect/issues/89) | `components/ui/`: button (44px targets, four variants), input/select/textarea + field wrapper, card, badge, table, code block, empty state. Scaffold `HelloWorld.vue` deleted. |
| [#90](https://github.com/suporterfid/taskconnect/issues/90) | Status tones as §14 extensions: success / warning / danger / info / muted, each measured against **both** `canvas` and `surface`, each paired with an icon. Centralized mapping replaces `TaskListPage.statusClass` and `ApiKeysPage.statusBadgeClass`. |

### Migration

| Issue | Surface | Files | Utilities |
|---|---|---:|---:|
| [#91](https://github.com/suporterfid/taskconnect/issues/91) | Auth + dashboard | 4 | 134 |
| [#92](https://github.com/suporterfid/taskconnect/issues/92) | Tasks / runs / DLQ / pipelines | 8 | 435 |
| [#93](https://github.com/suporterfid/taskconnect/issues/93) | Admin + configuration | 10 | 573 |
| [#94](https://github.com/suporterfid/taskconnect/issues/94) | Blade shell, welcome page, email | 3 | — |

Each batch shrinks the #86 allowlist; after #93 the guard becomes unconditional.

### Identity, motion, proof

| Issue | Decision |
|---|---|
| [#95](https://github.com/suporterfid/taskconnect/issues/95) | Delete the third-party favicon and Bluesky/Discord/GitHub/X sprite; author a TaskConnect mark; `assets/brand/` per §12 with licenses recorded. |
| [#97](https://github.com/suporterfid/taskconnect/issues/97) | Motion tokens applied; global reduced-motion guard **plus** a real static alternative for the spinner (a 0.01ms animation is a frozen glyph, not an accommodation). |
| [#98](https://github.com/suporterfid/taskconnect/issues/98) | Playwright + axe sweep with `color-contrast` **enabled**, en and pt-BR, 200% zoom, 360px width, keyboard order. Plus a Vitest test asserting each documented contrast pair. Gates the epic close. |
| [#99](https://github.com/suporterfid/taskconnect/issues/99) | `docs/visual-identity.md`: shipped tokens, extensions, departures, measured contrast table, brand index, filled-in §13 checklist. Closes the epic. |

## Sequencing

```
#86 → #87 → #88 → #96 → #89 → #90 ─┬→ #91
                                    ├→ #92
                                    ├→ #93
                                    └→ #94   →  #98  →  #99
#95, #97 run in parallel from the start
```

## Open decisions (owned by the epic, recorded in #99)

1. Dark-only vs. maintaining a light theme — recommended dark-only.
2. In-app heading scale vs. the literal §4.2 marketing scale — recommended documented step-down.
3. Status palette values — must be measured, not chosen by eye.
4. Email stays light — a deliberate §14 departure.
5. `welcome.blade.php`: theme it or redirect `/` → `/login` — recommended redirect.

## Non-goals

- Information architecture or UX changes; this is a re-skin over a token layer.
- A user-facing light theme (possible future §14 extension).
- Design-system package or Storybook.
- Any API, backend, or scheduler change.
