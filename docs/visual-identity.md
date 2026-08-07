# TaskConnect Visual Identity Specification

## 1. Purpose, audience, and boundary

This document is the implementation contract for TaskConnect's digital product UI. It is written for product designers, frontend engineers, accessibility reviewers, localization engineers, and maintainers. The normative words **MUST**, **SHOULD**, and **MAY** mean required, recommended unless a documented exception exists, and optional.

TaskConnect uses a quiet, editorial, content-first interface with warm neutrals and restrained blue actions. It takes high-level inspiration from modern workspace products, including Notion, but is not a copy. TaskConnect MUST NOT use Notion trademarks, logos, copy, screenshots, illustrations, proprietary fonts, or other proprietary assets. All product copy and visual assets must be original.

The scope is digital product UI: the Vue application, the no-JavaScript Laravel error shell, tokens, typography, layout, components, interaction, responsive behavior, theme behavior, accessibility, and localization. It does not govern logos, print, campaigns, marketing pages, or transactional email presentation. The target is WCAG 2.2 Level AA.

CSS custom properties in `frontend/src/style.css` are the source of truth. `resources/css/app.css` mirrors the standalone Laravel shell. Components MUST consume semantic tokens or aliases that resolve directly to them; component-specific hex, `rgb()`, and `hsl()` values are prohibited.

## 2. Principles

- **Quiet utility:** hierarchy, type, and spacing support the task; decoration remains secondary.
- **Content first:** titles, content, and the current action are more prominent than application chrome.
- **Warm-neutral foundation:** light and dark canvases avoid glare without introducing a decorative brand tint.
- **Restrained semantic color:** blue communicates links/actions; status colors retain status meaning and always have text or icon reinforcement.
- **Compact visuals, reachable controls:** every pointer target is at least 44Ã—44 CSS px even when its visible control appears dense.
- **International by construction:** text expansion, RTL, mixed direction, script shaping, and locale formatting are layout inputs, not later patches.
- **Visible state:** hover, active, selected, invalid, loading, disabled, and keyboard focus are distinct without depending on motion or color alone.

## 3. Semantic color contract

All values are sRGB. Normal text pairs MUST reach 4.5:1, large text and essential non-text boundaries MUST reach 3:1, and focus indicators MUST remain visible against adjacent colors. Disabled controls are exempt from WCAG text contrast only while disabled and MUST NOT carry essential information.

| Token | Light | Dark | Shipped purpose |
|---|---:|---:|---|
| `--color-bg-canvas` | `#FFFFFF` | `#191919` | Application canvas |
| `--color-bg-surface` | `#F7F6F3` | `#202020` | Secondary surface |
| `--color-bg-elevated` | `#FFFFFF` | `#252525` | Menus, dialogs, raised cards |
| `--color-bg-hover` | `#EFEDEA` | `#2C2C2C` | Neutral hover fill |
| `--color-bg-selected` | `#E7F0FA` | `#123B60` | Selected/current item |
| `--color-text-primary` | `#252525` | `#F1F1EF` | Primary text |
| `--color-text-secondary` | `#5F5F5F` | `#C6C6C2` | Supporting text |
| `--color-text-disabled` | `#929292` | `#888884` | Disabled non-essential text |
| `--color-text-inverse` | `#FFFFFF` | `#191919` | Inverse content |
| `--color-text-link` | `#0F5EAB` | `#79B8E8` | Underlined links |
| `--color-border-default` | `#D9D7D3` | `#4A4A4A` | Decorative separators only |
| `--color-border-strong` | `#8A8882` | `#6E6E6E` | Essential control boundary |
| `--color-action-primary` | `#1A6DC1` | `#529CCA` | Primary action fill |
| `--color-action-primary-hover` | `#14599E` | `#70B4DE` | Primary action hover |
| `--color-action-primary-active` | `#104B86` | `#3E83B5` | Primary action pressed |
| `--color-action-primary-content` | `#FFFFFF` | `#111111` | Content on primary action |
| `--color-action-primary-subtle` | `#E7F0FA` | `#173755` | Quiet action background |
| `--color-focus-ring` | `#1A6DC1` | `#79B8E8` | Keyboard focus ring |
| `--color-success-fg` | `#126B3A` | `#7CDA9A` | Success content |
| `--color-success-bg` | `#F1FAF4` | `#13291C` | Success surface |
| `--color-success-border` | `#7CCB98` | `#34794C` | Success boundary |
| `--color-warning-fg` | `#7A4A00` | `#F5C775` | Warning content |
| `--color-warning-bg` | `#FFF7E6` | `#33250D` | Warning surface |
| `--color-warning-border` | `#F0B35A` | `#8D6418` | Warning boundary |
| `--color-danger-fg` | `#B42318` | `#F4A49E` | Danger content/inset cue |
| `--color-danger-bg` | `#FFF1F0` | `#381B1B` | Danger surface |
| `--color-danger-border` | `#F29A93` | `#8E4540` | Supporting danger cue |
| `--color-info-fg` | `#0F5EAB` | `#9DCCF2` | Information content |
| `--color-info-bg` | `#EDF5FE` | `#102B45` | Information surface |
| `--color-info-border` | `#85BCEB` | `#3D78AA` | Information boundary |

The strong boundary, not a status border, is the outer boundary for form controls and destructive buttons. Invalid controls additionally use a danger inset cue and visible/programmatic error text. Status foregrounds are paired only with their matching backgrounds. Primary action content is paired only with its matching fill.

Measured contrast ratios are executable in `frontend/src/style.contrast.spec.ts`. Key light/dark results are primary text on canvas 15.33/15.55:1, secondary text on canvas 6.39/10.26:1, links on canvas 6.54/8.23:1, primary-action content 5.25/6.26:1, strong boundaries on canvas 3.54/3.45:1, and focus rings on canvas 5.25/8.23:1.

## 4. Typography

Inter 4.1 variable roman is self-hosted under SIL OFL 1.1 with `font-display: swap`. Its binary and license are `frontend/src/assets/fonts/inter/inter-4.1-variable.woff2` and `INTER-LICENSE.txt`. Source Serif 4 and IBM Plex Mono are optional installed/system stack entries and never external runtime requests.

```css
--font-ui: Inter, "Noto Sans", "Noto Sans Arabic", "Noto Sans Hebrew", "Noto Sans SC", "Noto Sans TC", "Noto Sans JP", "Noto Sans KR", "Noto Sans Thai", "Noto Sans Devanagari", Arial, sans-serif;
--font-editorial: "Source Serif 4", "Noto Serif", "Noto Naskh Arabic", "Noto Serif Hebrew", "Noto Serif SC", "Noto Serif TC", "Noto Serif JP", "Noto Serif KR", Georgia, serif;
--font-code: "IBM Plex Mono", "Noto Sans Mono", "Noto Sans SC", "Noto Sans TC", "Noto Sans JP", "Noto Sans KR", monospace;
```

| Role | Font size / line height | Weight |
|---|---:|---:|
| Caption/metadata | `12px / 16px` | 400 or 500 |
| Compact UI/body | `14px / 20px` | 400 |
| Body | `16px / 24px` | 400 |
| Section title | `20px / 28px` | 600 |
| Page subheading | `24px / 32px` | 600 |
| Page title | `32px / 40px` | 700 |
| Display title | `44px / 52px` | 700 |

Only weights 400, 500, 600, and 700 are supported. Tables, timestamps, timers, and aligned numbers use tabular numerals. Code disables ligatures. The closest container MUST carry the correct `lang`. Arabic and Devanagari MUST not use letter spacing or character wrappers; CJK MAY use strict line breaking; Noto fallbacks provide Arabic, Hebrew, CJK, Thai, Devanagari, Latin, Cyrillic, and Greek coverage without forcing Latin glyphs.

## 5. Spacing, shape, elevation, icons, and motion

| Contract | Exact values |
|---|---|
| Spacing | `0, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64px` |
| Control inline padding | `12px` compact; `16px` regular |
| Visual control minimum | `32px` compact; `40px` regular |
| Pointer target | 44Ã—44 CSS px minimum |
| Radii | `2px` inline, `4px` control, `6px` card, `8px` dialog, `999px` pill |
| Borders | `1px` default; `2px` focus/error emphasis |
| Focus | `2px solid var(--color-focus-ring)` with `2px` offset |
| Icons | `16px` inline, `20px` control, `24px` standalone; stroke 1.5 or 2 |
| Motion | `120ms` feedback, `180ms` state, `240ms` entry/exit |
| Easing | standard `cubic-bezier(0.2, 0, 0, 1)`; exit `cubic-bezier(0.4, 0, 1, 1)` |

Elevation is `--shadow-0: none`, `--shadow-1: 0 1px 2px rgb(0 0 0 / 0.08)`, `--shadow-2: 0 4px 12px rgb(0 0 0 / 0.14)`, and `--shadow-3: 0 12px 28px rgb(0 0 0 / 0.18)`. Cards use level 1 sparingly, popovers level 2, and dialogs level 3. Dark mode relies more on surface/border separation and does not increase opacity.

Lucide icons are wrapped by `AppIcon`. Icons are decorative by default; icon-only actions require an accessible label. Only directional icons marked by the wrapper mirror in RTL. Logos, search, close, settings, edit, delete, status, media, charts, clocks, code, and numbers never mirror.

`prefers-reduced-motion` with the `reduce` value reduces animations/transitions to 1ms, disables repeated animation and smooth scrolling, and removes skeleton shimmer. State meaning never depends on animation.

Layer order is base 0, sticky header 10, sidebar overlay 20, popover/menu/tooltip 30, toast 40, dialog/scrim 50, and critical blocking overlay 60.

## 6. Theme algorithm

The public preference is exactly `light | dark | system` and is persisted only after an explicit choice under `taskconnect.theme`.

1. Before CSS and the application load, `frontend/public/theme-init.js` reads the preference in both the Vite and Blade shells.
2. Explicit `light` or `dark` resolves directly and ignores OS changes.
3. `system`, an absent value, invalid data, or inaccessible storage resolves through `prefers-color-scheme`.
4. While system or absent preference is active, live OS changes update the resolved theme.
5. The runtime synchronizes `data-theme`, the temporary `.dark` compatibility bridge, inline `color-scheme`, and the browser `theme-color` meta value.
6. Native controls inherit the resolved `color-scheme`; every authored surface consumes semantic tokens.

The blocking external bootstrap appears before application styles/scripts, preventing a first-paint mismatch without requiring inline code. Storage exceptions fail safely. `forced-colors` with the `active` value permits system colors, preserves native controls, removes shadow-only boundaries, and keeps current/selected/invalid/focus cues. No raster asset is inverted by CSS.

## 7. Structural internationalization

Every user-visible string MUST use a complete vue-i18n message key in both `en` and `pt-BR`; strings are never assembled from translated fragments. Language names are autonyms (English, PortuguÃªs), never flags. Locale-aware numbers, dates, times, relative times, pluralization, lists, and sorting use `Intl` APIs and locale-aware comparison rather than English formatting.

Layout uses CSS logical properties for margin, padding, inset, borders, sizes, alignment, and safe areas. `main.ts` applies both `lang` and `dir`. Arabic, Hebrew, Persian, Urdu, and `ar-XB` resolve RTL; current Latin and CJK shipping locales resolve LTR. Focus order follows DOM reading order in either direction.

- Short labels MUST survive 2Ã— expansion; general copy MUST survive at least 30% expansion.
- Controls, headings, menus, buttons, errors, toasts, and tags grow or wrap; fixed text heights and silent truncation are prohibited.
- Mixed-direction IDs, email, URLs, tokens, and code use `dir="auto"`, `<bdi>`, or bidi isolation.
- Arabic joining and Hebrew direction remain intact; Thai and Devanagari grapheme clusters are not split.
- CJK text wraps at linguistic opportunities; `overflow-wrap: anywhere` is reserved for unbroken identifiers.
- IME composition MUST NOT trigger validation, transformation, submission, or rerender data loss.
- Synthetic `en-XA` verifies expansion. Synthetic `ar-XB` verifies RTL geometry, mixed direction, and selective mirroring; these are test inputs, not shipped locale bundles.

## 8. Responsive application shell

The app shell contains a sidebar/navigation region, a top bar, page header/breadcrumb space, and main content. Reading views use a 720px maximum; database/data views use at most 1200px. Page headers and actions wrap before labels truncate.

| Viewport | Shipped behavior |
|---|---|
| `<480px` | Single column; sidebar is an inert/hidden modal drawer until opened; header returns to document flow; 16px logical page padding plus safe areas. |
| `480â€“767px` | Overlay drawer; sticky header; 20px logical page padding plus safe areas. |
| `768â€“1023px` | Persistent sidebar; labeled tables scroll within their own region. |
| `1024â€“1279px` | Persistent sidebar and full actions when space allows. |
| `â‰¥1280px` | Persistent sidebar; 1200px data views preserve 720px prose measure. |

Safe-area left/right values map to logical inline start/end and swap under RTL; top/bottom map to block start/end. Closed mobile navigation uses `inert` and `aria-hidden`; opening restores keyboard access, Escape closes it, and desktop restoration removes suppression.

At 320 CSS px, required content MUST not overlap, clip, create page-wide horizontal scrolling, or become keyboard-inaccessible. Only labeled, keyboard-focusable table/code regions may scroll horizontally. At 200% text resizing, and at a separate 200% browser zoom check, controls remain visible and operable. The header is in flow under 480px so a focused terminal control cannot be obscured.

## 9. Component and state contract

All controls need an accessible name, semantic HTML before ARIA, visible `:focus-visible`, a 44Ã—44 target or non-overlapping equivalent, natural wrapping, and semantic tokens in both themes.

| Family | Visual/state contract | Keyboard, accessibility, wrapping, and direction |
|---|---|---|
| Navigation/sidebar/top bar/breadcrumbs/tabs | Hover uses neutral hover; current uses selected fill plus border/weight; disabled is non-actionable. | Named `nav`; current page is programmatic; drawer opens/closes with named buttons and Escape; tab patterns use arrows; directional chevrons alone mirror. |
| Buttons/icon buttons/links | Primary uses action fill/content and hover/active tokens; destructive uses danger surface/inset plus strong outer border; links are underlined by default. | Native buttons use Enter/Space; icon buttons are named; labels wrap and controls grow; standalone action links have 44px targets. |
| Form controls | Strong boundary in all states; invalid adds danger inset and visible error; disabled remains legible. | Associated labels and descriptions; native keyboard semantics; fieldsets group choices; textareas grow/scroll internally; IME composition is preserved. |
| Cards and lists | Static cards are quiet; actionable cards expose hover, active, focus, selected, disabled, and error states as applicable. | One coherent control avoids nested actions; rows have names; values wrap and use bidi isolation where needed. |
| Database tables | Header/row boundaries, tabular numerals, non-color sort/current cues. | Wrapped by labeled `.table-scroll` regions with `role="region"` and `tabindex="0"`; only the region scrolls horizontally. |
| Tags and callouts | Matching status foreground/background/border plus text/icon meaning. | Wrap safely, preserve full labels, localize, and follow surrounding direction. |
| Menus | Elevated surface, hover/active/selected/disabled/destructive/focus states. | Menu-button behavior, arrow navigation where implemented, Escape close, and focus restoration. |
| Dialogs | Scrim and elevated surface remain distinct in both themes. | Accessible name, initial/trapped focus, Escape close when allowed, and focus restoration to opener. |
| Tooltips | Elevated semantic surface; never the only location for essential information. | `role="tooltip"`, `aria-describedby`, hover/focus opening, Escape dismissal, and hoverable persistence. |
| Toasts | Status text/icon/border; persistent messages offer dismissal. | Do not steal focus; `role="status"` for updates and `role="alert"` only for urgent interruption. |
| Skeletons | Neutral surfaces only; no status color; reduced motion removes shimmer. | Preserve layout and avoid repeated announcements. |
| Empty, loading, and error states | Concise empty action; stable loading layout; danger error plus a recovery path. | Announce meaningful state changes without noise; preserve recoverable form values; all copy wraps and localizes. |

Selected state always has a non-color marker. Hover is never the only affordance. Disabled controls are not focusable unless an explanation requires it; invalid controls remain focusable and programmatically described.

## 10. Microcopy

Copy is concise, direct, and translatable. It names the action and result, avoids positional wording such as "on the left," never relies on punctuation to join fragments, and avoids grammar encoded in DOM order. Destructive labels state the consequence. Icon-only controls always have localized names.

## 11. Transactional email exception

`resources/views/mail/task-run-failed-html.blade.php` deliberately remains a static light, table-based transactional email. Email clients cannot reliably consume the runtime token/theme contract, often strip CSS, and apply inconsistent dark-mode inversion. This exception does not authorize inaccessible styling: email still requires accessible static contrast, semantic structure, meaningful plain-text parity, localization, and testing in supported clients. Product UI and Laravel error pages are not covered by this exception.

## 12. Token governance

Token changes require design and engineering ownership. A new token MUST be semantic, support at least two anticipated uses, define both light and dark values, document valid foreground pairings, pass contrast tests, and include forced-colors behavior where relevant. One-off decorative tokens are rejected.

Component aliases MAY exist only as direct bridges to a public semantic token. Deprecation retains an alias, documents the replacement and migration version, updates all consumers, and removes it only in a major version. Documentation-only corrections are patch changes, additive tokens are minor changes, and removals or meaning changes are major changes. Contrast, focus, responsive, typography, RTL, and locale changes require accessibility and localization review.

## 13. Design handoff checklist

- [ ] Light and dark token values and valid foreground/background pairs are named.
- [ ] Every component state includes keyboard focus, disabled, invalid, selected, loading, and wrapping behavior where applicable.
- [ ] 720px reading and 1200px data measures, breakpoints, safe areas, and narrow behavior are shown.
- [ ] Copy has message keys, expansion budgets, formatting rules, and RTL/icon decisions.
- [ ] Text, action, focus, boundary, and status contrast evidence is attached.
- [ ] Visual assets are original, carry alt-text intent, and work in both themes and forced colors.

## 14. Engineering handoff checklist

- [ ] Components contain no raw palette utilities or component-specific color literals.
- [ ] Theme startup, storage failures, explicit preference, system changes, reload, `color-scheme`, and theme-color are tested.
- [ ] All `en` keys exist in `pt-BR`; document `lang` and `dir` update together.
- [ ] LTR/RTL, 2Ã— labels, 30% copy, mixed-direction data, CJK, Arabic, Hebrew, Thai, Devanagari, Cyrillic, and Greek cases are covered.
- [ ] Keyboard, focus, 44Ã—44 targets, 320 CSS px reflow, 200% text resizing, reduced motion, and forced colors pass.
- [ ] Table and code overflow stays inside labeled keyboard-operable regions.
- [ ] Frontend tests/build, backend tests, Playwright/axe, release build, and artifact audit pass before release.
- [ ] `theme-init.js`, compiled semantic CSS, Inter and its license, English/Portuguese resources, and the Laravel error shell are present in the artifact.

## 15. Verification sources

- Semantic source: `frontend/src/style.css`
- Laravel shell mirror: `resources/css/app.css`
- Theme runtime: `frontend/public/theme-init.js` and `frontend/src/theme.ts`
- Token/contrast guards: `frontend/src/style.spec.ts` and `frontend/src/style.contrast.spec.ts`
- Component/layout guards: `frontend/src/identity.components.spec.ts` and focused component specs
- Browser/axe proof: `frontend/e2e/a11y.spec.ts` and `frontend/e2e/identity.spec.ts`
- External tracking: [GitHub issue #132](https://github.com/suporterfid/taskconnect/issues/132)
- Inspiration only: [Notion](https://www.notion.com/) and [Notion content styling](https://www.notion.com/help/customize-and-style-your-content)
