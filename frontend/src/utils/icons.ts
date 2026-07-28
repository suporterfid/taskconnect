import type { FunctionalComponent } from 'vue'
import {
  Archive,
  Ban,
  Circle,
  CircleCheck,
  CirclePause,
  CircleX,
  Info,
  Loader,
  TriangleAlert,
} from 'lucide-vue-next'

/**
 * One name per concept, centralized so status → icon stays in sync with
 * status → tone (#90) instead of being re-picked ad hoc at each call site.
 * `running` is deliberately the static Loader glyph, not an animated
 * spinner — components decide separately whether to animate it (and must
 * respect prefers-reduced-motion, #97, if they do). See #96.
 */
export type SemanticIcon =
  | 'success'
  | 'warning'
  | 'danger'
  | 'info'
  | 'running'
  | 'paused'
  | 'archived'
  | 'dead'
  | 'neutral'

export const semanticIcons: Record<SemanticIcon, FunctionalComponent> = {
  success: CircleCheck,
  warning: TriangleAlert,
  danger: CircleX,
  info: Info,
  running: Loader,
  paused: CirclePause,
  archived: Archive,
  dead: Ban,
  neutral: Circle,
}
