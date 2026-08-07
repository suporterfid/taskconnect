/**
 * Shared class and attribute recipe for tables. Existing data views keep
 * their own column shapes while converging on one labelled, focusable scroll
 * region that prevents page-wide horizontal overflow.
 *
 * - `wrapper`: horizontal-scroll container so a wide table scrolls instead
 *   of breaking the page layout at 200% zoom (§10).
 * - `table`: border separators between rows rather than a heavy divider.
 * - `th`: muted, not a raw text color, so headers read as secondary to data.
 * - `td`: standard cell padding/text tone.
 * - `numeric`: tabular numerals for run counts/durations/attempt numbers
 *   (§4.4) — apply alongside `td` on numeric columns.
 *
 * See #89.
 */
export const tableClasses = {
  wrapper: 'table-scroll',
  table: 'min-w-full divide-y divide-border',
  th: 'px-3 py-2 text-start text-sm font-medium text-muted',
  td: 'px-3 py-2 text-sm text-text',
  numeric: 'tabular-nums',
} as const

/** Apply with a localized `aria-label` to every labelled horizontal-scroll region. */
export const tableRegionAttributes = {
  role: 'region',
  tabindex: 0,
} as const
