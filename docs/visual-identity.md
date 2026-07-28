# TaskConnect visual identity

This is TaskConnect's instantiation of the shared, project-neutral
[`visual-identity-spec.md`](visual-identity-spec.md). That file defines the contract —
dark-by-default, semantic color roles, a restrained purple accent, WCAG 2.2 AA — this file
records which values TaskConnect shipped for it, what was measured, and where this project
deliberately departs from the shared spec, per §14's extension policy. Section references
below (`§3.2`, `§14`, …) point at the shared spec unless noted otherwise.

Components must consume the semantic tokens below (`bg-canvas`, `text-muted`, `bg-action`,
…), never a raw palette value directly — that constraint is enforced by
`frontend/src/style.spec.ts`'s guard test, which fails the build if a raw Tailwind palette
utility (`bg-gray-500`, `text-violet-600`, …) reappears anywhere in `src/pages/`.

## Shipped tokens

Defined once in `frontend/src/style.css`'s `@theme static` block, and mirrored in
`resources/css/app.css` for the Blade shell (see [Departures](#recorded-departures) — the
Blade shell isn't currently reachable behind any route, so this mirror is dormant, but the
two files are kept in sync so it's correct the moment it is wired up).

### Core palette (theme-construction only — never referenced directly by components)

| Token | Value |
| --- | --- |
| `--color-neutral-0` | `#ffffff` |
| `--color-neutral-100` | `#ebebeb` |
| `--color-neutral-200` | `#d3d3d3` |
| `--color-neutral-300` | `#d0d0d0` |
| `--color-neutral-500` | `#b0b0b0` |
| `--color-neutral-950` | `#000000` |
| `--color-purple-500` | `#814dde` |
| `--color-purple-800` | `#1f0d69` |
| `--color-purple-900` | `#1b0f46` |
| `--color-purple-950` | `#1a0a3e` |

`--color-purple-500` (`#814dde`) is the shared spec's designated accent value verbatim
(§3.1) — TaskConnect did not pick a different brand purple.

### Semantic roles (the public component API)

| Token | Value | Built from |
| --- | --- | --- |
| `--color-canvas` | `#000000` | `--color-neutral-950` |
| `--color-surface` | `#1a0a3e` | `--color-purple-950` |
| `--color-surface-emphasis` | `#1b0f46` | `--color-purple-900` |
| `--color-text` | `#ebebeb` | `--color-neutral-100` |
| `--color-text-muted` | `#b0b0b0` | `--color-neutral-500` |
| `--color-text-inverse` | `#000000` | `--color-neutral-950` |
| `--color-border` | `rgb(208 208 208 / 24%)` | — |
| `--color-border-strong` | `rgb(211 211 211 / 48%)` | — |
| `--color-action` | `#814dde` | `--color-purple-500` |
| `--color-action-hover` | `#1f0d69` | `--color-purple-800` |
| `--color-focus` | `#814dde` | `--color-purple-500` |

### Shape, spacing, motion

| Token | Value |
| --- | --- |
| `--spacing` | `0.25rem` (4px base unit) |
| `--container-reading` | `45rem` |
| `--container-app` | `80rem` |
| `--radius-sm` / `--radius-md` / `--radius-lg` / `--radius-pill` | `0.25rem` / `0.5rem` / `0.75rem` / `999px` |
| `--duration-fast` / `--duration-standard` / `--duration-slow` | `120ms` / `180ms` / `240ms` |
| `--ease-standard` | `cubic-bezier(0.2, 0, 0, 1)` |
| `--font-sans` | `"Open Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif` |
| `--font-mono` | `ui-monospace, "SFMono-Regular", Consolas, "Liberation Mono", monospace` |

## Project extensions (§14)

Each of these adds to the shared spec rather than replacing it, per §14's five rules —
semantic naming, preserved token meanings, full interaction-state coverage, documented
contrast, no near-duplicates.

### Status colors (#90)

The shared spec deliberately leaves status colors to the project (§3.2). TaskConnect ships
five: `success`, `warning`, `danger`, `info`, and `neutral` (which reuses `--color-text-muted`
rather than a new hue, since archived/revoked/cancelled states are terminal-but-benign, not
attention-worthy).

| Token | Value | vs. `canvas` | vs. `surface` |
| --- | --- | ---: | ---: |
| `--color-success` | `#4ade80` | 12.05:1 | 10.42:1 |
| `--color-warning` | `#fbbf24` | 12.58:1 | 10.87:1 |
| `--color-danger` | `#f87171` | 7.59:1 | 6.56:1 |
| `--color-info` | `#7dd3fc` | 12.60:1 | 10.89:1 |

Every ratio is measured (WCAG relative-luminance formula), not eyeballed, against **both**
backgrounds a status color can appear on — a badge or alert may sit on the page canvas or
inside a card. `--color-danger-strong` (`#dc2626`) and `--color-danger-strong-hover`
(`#b91c1c`) are a deliberately separate, lower-contrast-as-foreground pair: `BaseButton`'s
solid danger fill needs white text on top, and white-on-`danger` itself is only 2.77:1
(fails) while white-on-`danger-strong` is 4.83:1. The button fill and the
foreground/border/badge-tint uses are genuinely different colors serving different roles,
not one token reused two ways.

These measurements are codified as an executable contract in
`frontend/src/style.contrast.spec.ts` (added for #98) — it reads the token values directly
out of `style.css` and fails if an edit silently drops a pair below its required ratio, and
`frontend/e2e/a11y.spec.ts` re-verifies the same rule with axe-core in a real browser.

Each status tone always pairs with an icon (`frontend/src/utils/icons.ts`'s `semanticIcons`
map) and a text label (`BaseBadge` requires a `label` prop, not just a slot) — §3.3's "never
color alone" rule. See [Icon family](#icon-family-96) below.

### In-app type scale (#87)

§4.2's display scale (a 40px, uppercase `h2`) is tuned for marketing/auth surfaces, and §4.2
itself directs product UIs toward the 16px body style instead. `frontend/src/style.css`
therefore layers a step-down specifically for `AppLayout`-hosted pages (`.app-shell h1` /
`.app-shell h2`), dropping the uppercase treatment and shrinking headings for a dense
operator console, while `LoginPage`/`ForgotPasswordPage`/`ResetPasswordPage` (outside
`.app-shell`) keep the full marketing-tuned scale from §4.2. Existing page-level heading
utilities (explicit Tailwind font-size/weight classes already on individual `<h1>`/`<h2>`
elements) take precedence over both base rulesets, so this establishes the base layer without
changing any already-migrated page's heading today.

### Spacing mapping (#86)

§5's `space-*` table (a 4px base unit, most spacing in multiples of 8px) is expressed through
Tailwind's existing default numeric spacing scale (`--spacing: 0.25rem` in the `@theme`
block) rather than duplicate named tokens — `p-1` through `p-24` already resolve to exactly
the shared spec's `space-1` through `space-24` px values. §14.5 forbids near-duplicate
spacing values, so no parallel `space-*` custom properties were added.

### Icon family (#96)

[`lucide-vue-next`](https://lucide.dev) (ISC license), wrapped by `frontend/src/components/AppIcon.vue`
so every icon in the app gets the same size scale (`16` / `20` / `24`, default `20`) and the
same accessible defaults: decorative by default (`aria-hidden`, no accessible name) unless a
`label` prop is passed, in which case it becomes `role="img"` with an `aria-label` for a
meaningful icon with no adjacent text. Status-tone icons are centralized in
`frontend/src/utils/icons.ts`'s `semanticIcons` map so status → icon never drifts independently
of status → tone (#90).

## Recorded departures

Per §14's closing paragraph — decisions made next to this project's theme so a future
contributor understands why, rather than "fixing" them back toward the shared spec's letter.

### Dark-only, not `dark:` (#88)

§2 treats dark as *the* environment for this kind of product, not a user-selectable mode.
TaskConnect ships dark-only: both HTML shells (`frontend/index.html` and
`resources/views/app.blade.php`) set `class="dark"` unconditionally rather than gating on
`prefers-color-scheme` or exposing a light/dark toggle. A light theme was never implemented
or verified, so keeping a `dark:` variant path around would have meant maintaining an
unverified, half-supported second theme; removing it entirely is the more honest state.

### Transactional email stays light (#94)

`resources/views/mail/task-run-failed-html.blade.php` is a deliberate departure from §2's
dark canvas: email client dark-mode handling is inconsistent (Outlook ignores
`prefers-color-scheme`, many clients strip `<style>` blocks and invert colors
unpredictably), so a dark canvas in a transactional email risks unreadable text in a client
that partially "dark-modes" the markup. The email uses a light, table-based layout with
inline styles instead — see the comment at the top of that template for the full rationale.

## References

- Shared spec: [`docs/visual-identity-spec.md`](visual-identity-spec.md)
- Token source of truth: `frontend/src/style.css` (mirrored, currently dormant, in `resources/css/app.css`)
- Contrast verification: `frontend/src/style.contrast.spec.ts` (unit), `frontend/e2e/a11y.spec.ts` (real-browser axe sweep)
- Known, tracked gap: [#118](https://github.com/suporterfid/taskconnect/issues/118) — `--color-action` (`text-action` link color) measures below the 4.5:1 normal-text minimum on both `canvas` (4.05:1) and `surface` (3.50:1); fixing it means introducing and measuring a new text-only token across ~22 files, which needs a maintainer's sign-off on the exact replacement value before it lands.
