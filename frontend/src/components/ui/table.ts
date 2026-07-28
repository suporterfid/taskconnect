/**
 * Documented class recipe for tables (§7, §4.4, §10) — a component wasn't
 * built because every existing table (DlqPage, TaskListPage, RunListPage, …)
 * has a different column shape, and replacing those call sites is out of
 * scope here (#91/#92/#93). This gives them one recipe to converge on.
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
  wrapper: 'overflow-x-auto',
  table: 'min-w-full divide-y divide-border',
  th: 'px-3 py-2 text-left text-sm font-medium text-muted',
  td: 'px-3 py-2 text-sm text-text',
  numeric: 'tabular-nums',
} as const
