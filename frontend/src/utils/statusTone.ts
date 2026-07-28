import type { FunctionalComponent } from 'vue'

import { semanticIcons, type SemanticIcon } from './icons'
import type { RunState, TaskDefinitionStatus } from '@/services/types'

/**
 * The five status tones (§3.2 — deliberately not fixed by the shared spec).
 * Centralized here, alongside the icon each pairs with, so no page re-picks
 * a color for the same state and no state is ever conveyed by color alone
 * (§3.3). See #90.
 */
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

export interface StatusTone {
  tone: Tone
  icon: FunctionalComponent
}

const TONE_ICON: Record<Tone, SemanticIcon> = {
  success: 'success',
  warning: 'warning',
  danger: 'danger',
  info: 'info',
  neutral: 'neutral',
}

function of(tone: Tone): StatusTone {
  return { tone, icon: semanticIcons[TONE_ICON[tone]] }
}

// A truly unrecognized value (a future backend state this module hasn't been
// taught about yet) falls back to `neutral` rather than guessing — silently
// theming an unknown state as "success" or "danger" would be worse than a
// visibly bland one. Every *known* value is asserted against in
// statusTone.spec.ts so this fallback is never hit for a real state.
const FALLBACK = of('neutral')

export function toneForTaskStatus(status: TaskDefinitionStatus | string): StatusTone {
  switch (status as TaskDefinitionStatus) {
    case 'draft':
      return of('neutral')
    case 'active':
      return of('success')
    case 'paused':
      return of('warning')
    case 'completed':
      return of('neutral')
    case 'archived':
      return of('neutral')
    default:
      return FALLBACK
  }
}

export function toneForRunState(state: RunState | string): StatusTone {
  switch (state as RunState) {
    case 'pending':
      return of('info')
    case 'running':
      return of('info')
    case 'retry_wait':
      return of('warning')
    case 'succeeded':
      return of('success')
    case 'dead':
      return of('danger')
    case 'cancelled':
      return of('neutral')
    case 'blocked':
      return of('warning')
    default:
      return FALLBACK
  }
}

export type ApiKeyStatus = 'active' | 'expired' | 'revoked'

export function toneForApiKeyStatus(status: ApiKeyStatus | string): StatusTone {
  switch (status as ApiKeyStatus) {
    case 'active':
      return of('success')
    case 'expired':
      return of('warning')
    case 'revoked':
      return of('neutral')
    default:
      return FALLBACK
  }
}

export type PipelineInstanceStatus = 'pending' | 'running' | 'succeeded' | 'failed' | 'cancelled'

export function toneForPipelineInstanceStatus(status: PipelineInstanceStatus | string): StatusTone {
  switch (status as PipelineInstanceStatus) {
    case 'pending':
      return of('info')
    case 'running':
      return of('info')
    case 'succeeded':
      return of('success')
    case 'failed':
      return of('danger')
    case 'cancelled':
      return of('neutral')
    default:
      return FALLBACK
  }
}

export type PipelineNodeStatus = 'pending' | 'ready' | 'running' | 'succeeded' | 'failed' | 'skipped' | 'halted'

export function toneForPipelineNodeStatus(status: PipelineNodeStatus | string): StatusTone {
  switch (status as PipelineNodeStatus) {
    case 'pending':
      return of('info')
    case 'ready':
      return of('info')
    case 'running':
      return of('info')
    case 'succeeded':
      return of('success')
    case 'failed':
      return of('danger')
    case 'skipped':
      return of('neutral')
    case 'halted':
      return of('warning')
    default:
      return FALLBACK
  }
}

export type PlatformHealthStatus = 'healthy' | 'degraded'

export function toneForPlatformHealth(status: PlatformHealthStatus | string): StatusTone {
  switch (status as PlatformHealthStatus) {
    case 'healthy':
      return of('success')
    case 'degraded':
      return of('warning')
    default:
      return FALLBACK
  }
}
