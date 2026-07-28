# Generic Visual Identity Specification

## 1. Purpose

This specification defines a reusable visual foundation for open-source software,
documentation, product interfaces, and project websites. It favors a dark,
high-contrast aesthetic with restrained purple accents, clear typography, and
minimal ornamentation.

The system is intentionally independent of any project name, logo, framework, or
product category. Individual projects may extend it, but should preserve the
semantic roles and accessibility requirements described here.

## 2. Design Principles

### Clarity first

Content and actions must remain easy to scan. Typography, spacing, and contrast
should establish hierarchy before decoration is introduced.

### Dark by default

Near-black backgrounds create the primary visual environment. Lighter surfaces
and text provide structure without replacing the dark foundation.

### Purposeful accent

Purple is reserved for interaction, focus, selection, and small moments of
emphasis. Large areas should use the deeper purple shades or neutral colors.

### Restrained presentation

Prefer simple geometry, limited shadows, subtle borders, and generous negative
space. Avoid decorative effects that compete with the content.

### Consistency across projects

Components should consume semantic tokens such as `--color-action` or
`--color-text-muted`, rather than raw palette values. This allows a project to
adapt the theme without rewriting component styles.

## 3. Color System

### 3.1 Core palette

#### Neutral colors

| Token | Hex | Intended use |
| --- | --- | --- |
| `neutral-950` | `#000000` | Main canvas and maximum-contrast text |
| `neutral-0` | `#FFFFFF` | Inverse text, icons, and light-theme canvas |
| `neutral-100` | `#EBEBEB` | Primary text on dark backgrounds |
| `neutral-200` | `#D3D3D3` | Strong borders and disabled light surfaces |
| `neutral-300` | `#D0D0D0` | Secondary borders and separators |
| `neutral-500` | `#B0B0B0` | Muted text and secondary icons |

#### Accent colors

| Token | Hex | Intended use |
| --- | --- | --- |
| `purple-500` | `#814DDE` | Primary actions, links, focus, and selection |
| `purple-800` | `#1F0D69` | Active surfaces and prominent dark accents |
| `purple-900` | `#1B0F46` | Elevated panels and secondary surfaces |
| `purple-950` | `#1A0A3E` | Deep accent backgrounds and overlays |

Color names describe their role in the palette, not a project or product.

### 3.2 Semantic color roles

The default theme is dark:

| Semantic token | Palette value | Usage |
| --- | --- | --- |
| `canvas` | `neutral-950` | Page and application background |
| `surface` | `purple-950` | Cards, panels, menus, and code blocks |
| `surface-emphasis` | `purple-900` | Selected or elevated surfaces |
| `text` | `neutral-100` | Primary copy |
| `text-muted` | `neutral-500` | Metadata and supporting copy |
| `text-inverse` | `neutral-950` | Text on very light surfaces |
| `border` | `neutral-300` at 24% opacity | Default separators and outlines |
| `border-strong` | `neutral-200` at 48% opacity | Emphasized boundaries |
| `action` | `purple-500` | Links, buttons, focus rings, and selection |
| `action-hover` | `purple-800` | Hover or pressed action surfaces |
| `focus` | `purple-500` | Keyboard focus indicator |

Status colors for success, warning, danger, and information are deliberately not
fixed here. Projects should add them as semantic tokens and verify their contrast
against both `canvas` and `surface`.

### 3.3 Color usage rules

- Use `purple-500` for interactive emphasis, not for long text passages.
- Use neutral text colors for body copy.
- Avoid placing `purple-500` text on black for small text; use it for large text,
  icons, focus outlines, or filled controls.
- Use white text on `purple-500` for primary buttons.
- Do not rely on color alone to communicate state. Pair it with text, an icon,
  shape, or another visible cue.
- Keep gradients optional. When used, limit them to adjacent purple shades and
  keep text on a solid or sufficiently opaque backing surface.

### 3.4 Verified contrast combinations

The following pairs meet WCAG AA contrast for normal text:

| Foreground | Background | Contrast ratio |
| --- | --- | ---: |
| `#EBEBEB` | `#000000` | 17.62:1 |
| `#B0B0B0` | `#000000` | 9.68:1 |
| `#FFFFFF` | `#814DDE` | 5.19:1 |
| `#FFFFFF` | `#1F0D69` | 15.91:1 |
| `#EBEBEB` | `#1A0A3E` | 15.23:1 |

Re-test contrast whenever colors, opacity, font size, or backgrounds change.

## 4. Typography

### 4.1 Font family

Use Open Sans as the primary typeface:

```css
font-family: "Open Sans", -apple-system, BlinkMacSystemFont, "Segoe UI",
  Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
```

Supported weights are:

| Weight | Role |
| ---: | --- |
| `400` | Body copy and long-form content |
| `500` | Navigation, labels, and compact interface text |
| `600` | Buttons, emphasized labels, and small headings |
| `700` | Display and section headings |

Use real font files for each required weight. Do not synthesize bold or italic
styles when the selected font source does not provide them.

### 4.2 Desktop type scale

| Style | Size | Weight | Line height | Letter spacing | Case |
| --- | ---: | ---: | ---: | ---: | --- |
| Display / H1 | `3.2rem` (`51.2px`) | `700` | `1.2` | Normal | Sentence case |
| Section / H2 | `2.5rem` (`40px`) | `700` | `1.2` | `0.025em` | Uppercase |
| Subsection / H3 | `1.5rem` (`24px`) | `700` | `1.3` | Normal | Sentence case |
| Lead body | `1.2rem` (`19.2px`) | `400` | `1.6` | Normal | Sentence case |
| Body | `1rem` (`16px`) | `400` | `1.6` | Normal | Sentence case |
| Small / metadata | `0.875rem` (`14px`) | `400` or `500` | `1.5` | Normal | Sentence case |

The `19.2px` lead style is intended for introductions and spacious marketing
layouts. Product interfaces and documentation should normally use the `16px`
body style.

### 4.3 Responsive typography

Use fluid sizing for large headings while keeping body text stable:

```css
h1 {
  font-size: clamp(2.25rem, 1.7rem + 2.4vw, 3.2rem);
  line-height: 1.2;
}

h2 {
  font-size: clamp(1.75rem, 1.35rem + 1.75vw, 2.5rem);
  line-height: 1.2;
}
```

On narrow screens, H2 headings may use sentence case when uppercase text would
wrap excessively. Do not reduce body text below `16px` for primary content.

### 4.4 Typography rules

- Keep paragraphs between approximately 45 and 75 characters per line.
- Use no more than three font weights in a single view.
- Reserve uppercase styling for short headings, labels, and navigation.
- Do not use uppercase for paragraphs or multi-line instructions.
- Use underlines for links in body copy, at least on hover and keyboard focus.
- Align numbers in tables with tabular numerals when the font supports them.

## 5. Layout and Spacing

Use a `4px` base unit. Most component and layout spacing should use multiples of
`8px`.

| Token | Value | Common use |
| --- | ---: | --- |
| `space-1` | `4px` | Tight icon or inline spacing |
| `space-2` | `8px` | Compact internal spacing |
| `space-3` | `12px` | Form controls and small groups |
| `space-4` | `16px` | Standard component padding |
| `space-6` | `24px` | Card padding and content groups |
| `space-8` | `32px` | Section subdivisions |
| `space-12` | `48px` | Page sections |
| `space-16` | `64px` | Large section separation |
| `space-24` | `96px` | Marketing-page rhythm |

Recommended layout rules:

- Keep the main reading column between `640px` and `760px`.
- Keep general application content within a `1200px` to `1280px` container.
- Use a minimum horizontal page gutter of `16px` on small screens, `24px` on
  medium screens, and `32px` on large screens.
- Increase whitespace before a section more than after its heading.
- Align related controls and copy to a shared grid.

## 6. Shape, Borders, and Elevation

### 6.1 Corner radius

| Token | Value | Usage |
| --- | ---: | --- |
| `radius-sm` | `4px` | Tags, small controls, and code elements |
| `radius-md` | `8px` | Inputs, buttons, and compact cards |
| `radius-lg` | `12px` | Panels, dialogs, and feature cards |
| `radius-pill` | `999px` | Status badges and intentional pill controls |

Avoid mixing several radius styles within the same component family.

### 6.2 Borders

Use `1px` borders to distinguish surfaces. Prefer low-opacity neutral borders over
bright solid outlines. Interactive controls may use a stronger border on hover,
focus, invalid, or selected states.

### 6.3 Shadows

Dark interfaces should rely mainly on borders and background separation. When a
shadow is necessary, keep it broad and subtle:

```css
box-shadow: 0 12px 32px rgb(0 0 0 / 32%);
```

Do not use shadows as the only indication of keyboard focus or selection.

## 7. Core Interface Patterns

### Buttons

- Primary buttons use `action` as the background and white text.
- Secondary buttons use a transparent background, primary text, and `border`.
- Tertiary buttons appear as text actions and must retain a visible hover and
  focus state.
- Use a minimum target size of `44px` by `44px` for primary touch interactions.
- Labels should begin with a clear action verb where practical.

### Links

- Large or bold links may use `action`. Small body links use `text` with an
  underline, then use `action` for hover and focus emphasis.
- Visited-state styling is optional in applications and recommended in
  documentation or content-heavy websites.

### Inputs

- Inputs use `surface` or `canvas`, primary text, and a visible border.
- Focus uses a `2px` outer ring in `focus` with at least `2px` separation from the
  component edge.
- Validation messages appear next to the affected field and include text or an
  icon in addition to color.

### Cards and panels

- Use cards only when grouping improves comprehension or interaction.
- Default cards use `surface`; selected cards use `surface-emphasis` and a
  stronger border.
- Keep card padding consistent within a view.

### Code

- Use a system monospace stack for code and terminal content.
- Code blocks use `surface`, primary text, and a subtle border.
- Syntax colors must meet contrast requirements and should remain distinguishable
  without relying only on hue.

## 8. Imagery, Icons, and Marks

### Icons

- Use one icon family per project.
- Prefer simple outlined or consistently filled icons.
- Use `16px`, `20px`, and `24px` as the default sizes.
- Decorative icons should be hidden from assistive technology.
- Meaningful icons need an accessible name or adjacent visible label.

### Images and illustration

- Prefer high-contrast imagery with uncluttered focal points.
- Use purple overlays sparingly to unify mixed image sources.
- Provide descriptive alternative text for meaningful images.
- Avoid placing important text directly over visually complex imagery.

### Project marks

Each project may define its own wordmark or symbol. Keep it visually separate
from this shared system:

- Provide horizontal, compact, monochrome, and transparent-background variants.
- Define clear space based on a stable feature of the mark.
- Do not stretch, recolor, rotate, outline, or add effects to the mark.
- Ensure the mark remains identifiable at favicon and repository-avatar sizes.

## 9. Motion

- Keep interface transitions between `120ms` and `240ms`.
- Use standard easing for entry and exit:
  `cubic-bezier(0.2, 0, 0, 1)`.
- Animate opacity and transforms where possible; avoid unnecessary layout
  animation.
- Motion must explain state change, preserve context, or provide feedback.
- Respect `prefers-reduced-motion` and remove non-essential animation when it is
  enabled.

## 10. Accessibility Requirements

- Target WCAG 2.2 AA for public interfaces and documentation.
- Maintain a contrast ratio of at least `4.5:1` for normal text and `3:1` for
  large text and meaningful interface graphics.
- Preserve visible keyboard focus on every interactive element.
- Support keyboard navigation in logical reading order.
- Use semantic HTML before adding ARIA.
- Never encode information using only color, position, sound, or motion.
- Support browser zoom to at least 200% without loss of content or function.
- Test layouts at narrow widths and with longer translated strings.

## 11. Design Tokens

The following CSS custom properties provide a framework-neutral starting point:

```css
:root {
  color-scheme: dark;

  --color-neutral-0: #ffffff;
  --color-neutral-100: #ebebeb;
  --color-neutral-200: #d3d3d3;
  --color-neutral-300: #d0d0d0;
  --color-neutral-500: #b0b0b0;
  --color-neutral-950: #000000;

  --color-purple-500: #814dde;
  --color-purple-800: #1f0d69;
  --color-purple-900: #1b0f46;
  --color-purple-950: #1a0a3e;

  --color-canvas: var(--color-neutral-950);
  --color-surface: var(--color-purple-950);
  --color-surface-emphasis: var(--color-purple-900);
  --color-text: var(--color-neutral-100);
  --color-text-muted: var(--color-neutral-500);
  --color-text-inverse: var(--color-neutral-950);
  --color-border: rgb(208 208 208 / 24%);
  --color-border-strong: rgb(211 211 211 / 48%);
  --color-action: var(--color-purple-500);
  --color-action-hover: var(--color-purple-800);
  --color-focus: var(--color-purple-500);

  --font-sans: "Open Sans", -apple-system, BlinkMacSystemFont, "Segoe UI",
    Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", Arial, sans-serif;
  --font-mono: ui-monospace, "SFMono-Regular", Consolas, "Liberation Mono",
    monospace;

  --space-1: 0.25rem;
  --space-2: 0.5rem;
  --space-3: 0.75rem;
  --space-4: 1rem;
  --space-6: 1.5rem;
  --space-8: 2rem;
  --space-12: 3rem;
  --space-16: 4rem;
  --space-24: 6rem;

  --radius-sm: 0.25rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-pill: 999px;

  --duration-fast: 120ms;
  --duration-standard: 180ms;
  --duration-slow: 240ms;
  --ease-standard: cubic-bezier(0.2, 0, 0, 1);
}
```

Components should reference only semantic tokens. Raw palette tokens are intended
for theme construction and exceptional visual treatments.

## 12. Repository Asset Guidance

For consistent use across open-source repositories, keep shared identity assets
in a predictable structure:

```text
docs/
  visual-identity.md
assets/
  brand/
    mark.svg
    mark-monochrome.svg
    wordmark.svg
    social-card.png
    favicon.svg
```

Use SVG for scalable marks and icons. Use optimized PNG, WebP, or AVIF for raster
artwork as platform support allows. Do not commit editable source files unless
their license and contribution workflow are documented.

## 13. Adoption Checklist

- [ ] Semantic color tokens are mapped before components are styled.
- [ ] Text and controls meet WCAG AA contrast.
- [ ] Keyboard focus is visible and consistent.
- [ ] Open Sans and required weights are loaded with appropriate fallbacks.
- [ ] Heading hierarchy follows document structure, not visual size alone.
- [ ] Layout uses the shared spacing scale.
- [ ] Interactive targets are large enough for touch input.
- [ ] Motion respects reduced-motion preferences.
- [ ] Icons and images have appropriate accessible names or alternative text.
- [ ] Project-specific marks and status colors are documented separately.
- [ ] Repository assets are optimized and their licenses are recorded.

## 14. Extension Policy

A project may add tokens or patterns when the shared system does not cover a real
requirement. Extensions should:

1. Use semantic names rather than implementation-specific names.
2. Preserve the existing token meanings.
3. Include light, dark, hover, focus, disabled, and error behavior when relevant.
4. Document accessibility constraints and verified contrast pairs.
5. Avoid adding near-duplicate colors, spacing values, or component variants.

When a project intentionally departs from this specification, record the decision
next to its project-specific theme so future contributors understand the reason.
