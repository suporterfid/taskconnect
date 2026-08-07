# Notion-Inspired Visual Identity Migration Implementation Plan

> **For Codex:** Execute this plan with test-driven development and subagent-driven review. The canonical contract is `C:\workspace-offline\iroh\notion-inspired-visual-identity-spec.md`. GitHub issue [#132](https://github.com/suporterfid/taskconnect/issues/132) is the external source of truth.

**Goal:** Replace TaskConnect's dark-only purple presentation with a warm-neutral, content-first identity that supports accessible light/dark/system themes and treats i18n, RTL, responsive reflow, and semantic tokens as structural contracts.

**Architecture:** Keep Laravel 12, Vue 3, TypeScript, Vite, Tailwind v4, Pinia, and vue-i18n. Define the theme contract in CSS custom properties, add an external pre-paint bootstrap plus a typed runtime controller, expose preference through a localized Vue control, and migrate shared layout/primitives before auditing feature views. Use only semantic color roles in components and self-host all font assets.

**Toolchain:** Run project commands only through `scripts/tc.ps1` and Docker Compose. Use Vitest/Vue Test Utils for unit and contract tests, Playwright for browser evidence, and the existing Docker release command for production packaging.

---

## Task 1: Establish the semantic foundation and theme runtime

**Files:**
- Modify: `frontend/src/style.css`
- Modify: `resources/css/app.css`
- Modify: `frontend/index.html`
- Modify: `resources/views/app.blade.php`
- Create: `frontend/public/theme-init.js`
- Create: `frontend/src/theme.ts`
- Create/modify tests: `frontend/src/style.contrast.spec.ts`, `frontend/src/style.spec.ts`, `frontend/src/theme.spec.ts`
- Add: self-hosted Inter variable font and SIL OFL provenance beside existing frontend font assets

**Steps:**
1. Add failing tests for the complete 30-token light/dark matrix, required foreground pairings, exact theme preference values, startup precedence, media-query synchronization, storage safety, and no-flash shell inclusion.
2. Run the focused Vitest files through `scripts/tc.ps1 npm --prefix frontend run test -- ...` and record the intended RED failures.
3. Implement canonical light/dark semantic tokens, typography/space/radius/elevation/motion contracts, status foreground/background/border roles, forced-colors behavior, and reduced-motion behavior. Preserve legacy aliases only as semantic bridges.
4. Add the external pre-paint script and typed runtime for `light | dark | system`, persisted as `taskconnect.theme`, with `data-theme`, `color-scheme`, theme-color, and live OS synchronization.
5. Self-host Inter with license/provenance; retain Source Serif 4, IBM Plex Mono, and Noto-family script fallbacks in the stacks.
6. Re-run focused tests, `git diff --check`, and commit the foundation.

## Task 2: Add localized theme controls and structural directionality

**Files:**
- Modify: `frontend/src/main.ts`
- Modify: `frontend/src/i18n/index.ts` and locale message files
- Create: `frontend/src/components/ui/ThemeSelect.vue`
- Modify: `frontend/src/layouts/AppLayout.vue`
- Modify: `frontend/src/pages/SettingsPage.vue` where appropriate
- Add/modify focused Vue tests for theme selection, localization, keyboard use, and direction

**Steps:**
1. Add failing tests requiring English and Portuguese labels for theme preference, accessible native control semantics, document language/direction updates, and logical-layout classes.
2. Implement localized theme selection using the runtime controller and expose it consistently in the app shell/settings without duplicating state.
3. Derive `dir` from locale metadata, keep autonym language names, and replace physical left/right margin, padding, border, inset, alignment, and transform assumptions with logical equivalents.
4. Mirror only directional navigation icons; never mirror neutral/status icons or text.
5. Verify long Portuguese labels and synthetic RTL labels can wrap without clipping and all interactive targets remain at least 44×44 CSS px.
6. Run focused tests and commit.

## Task 3: Migrate the app shell and shared UI primitives

**Files:**
- Modify: `frontend/src/layouts/AppLayout.vue`
- Modify: shared files under `frontend/src/components/ui/`
- Modify: feature views only where they bypass shared primitives or semantic tokens
- Modify: `frontend/src/style.spec.ts` and component tests

**Steps:**
1. Add failing source/component tests for semantic-only colors, strong form boundaries, visible focus, status role triplets, 44px standalone actions, wrapping, and logical properties.
2. Implement the responsive shell: collapsible sidebar, top bar, breadcrumbs/page header behavior, 720px reading measure, wide data-view escape hatch, safe-area padding, 320px reflow, and 200% text resilience.
3. Migrate buttons, links, inputs, selects, textareas, cards, lists, tables, tags, callouts/alerts, menus, dialogs, tooltips, toasts, skeletons, and empty/loading/error states to semantic tokens and accessible interaction contracts.
4. Ensure invalid controls use a ≥3:1 essential boundary plus programmatic/text error cues; status communication must not rely on color alone.
5. Remove component-specific hard-coded colors and raw palette utilities from in-scope Vue/Blade surfaces; keep an explicit documented exception only if technically unavoidable.
6. Run focused and full frontend tests, then commit.

## Task 4: Documentation, production build, and browser proof

**Files:**
- Rewrite: `docs/visual-identity.md`
- Modify: relevant Playwright specs under `frontend/e2e/`
- Update: GitHub issue #132 checklist/evidence

**Steps:**
1. Make the adoption document self-contained: token tables for both themes, typography, layout, i18n/RTL rules, component contracts, theme algorithm, accessibility requirements, originality boundary, governance, and handoff checklists.
2. Add browser coverage for light/dark/system precedence and persistence, OS changes, keyboard/focus, long en-XA-style labels, ar-XB-style RTL, mixed direction, CJK wrapping, reduced motion, forced colors, 320px reflow, and actual 200% computed typography on representative short and long pages.
3. Run the full frontend test suite and type/build gate through `scripts/tc.ps1`.
4. Start the Docker application and run Playwright/axe with contrast enabled; capture numeric geometry/overflow/contrast evidence and screenshots for representative light/dark/mobile/RTL/forced-color states.
5. Run `scripts/tc.ps1 release`, inspect the artifact for theme runtime, CSS, font, and license, and run `git diff --check` plus a raw-color/source audit.
6. Update issue #132 with test/build/browser evidence and close it only after independent final review reports no open findings.

## Task 5: Independent final audit and closeout

**Files:**
- Review all commits from the pre-plan baseline through the implementation head
- Record review/fix evidence in `.superpowers/sdd/2026-08-07-notion-identity/`

**Steps:**
1. Review the complete diff against the canonical specification, repository constraints, issue #132, and this plan.
2. Resolve Important findings in bounded TDD fix rounds; do not defer identity, accessibility, i18n, RTL, or packaging requirements.
3. Re-run the affected focused tests after each fix and one fresh final verification set before completion.
4. Confirm no placeholders, proprietary Notion assets/copy, raw component colors, broken links, missing locale keys, or light/dark token gaps remain.
5. Record final evidence, close issue #132, and leave the branch ready for user review without pushing or merging unless separately authorized.
