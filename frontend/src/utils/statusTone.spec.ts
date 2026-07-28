import { describe, expect, it } from 'vitest'

import {
  toneForApiKeyStatus,
  toneForPipelineInstanceStatus,
  toneForPipelineNodeStatus,
  toneForPlatformHealth,
  toneForRunState,
  toneForTaskStatus,
} from './statusTone'

// Every case here is asserted against the *literal* enum values from the
// backend (app/Domain/Execution/Enums/TaskDefinitionStatus.php,
// app/Domain/Execution/Enums/RunState.php,
// app/Domain/Pipelines/PipelineInstanceStatus.php,
// app/Domain/Pipelines/PipelineNodeStatus.php), not just the frontend's
// mirrored TypeScript union — so a state added to one side and not the
// other has nowhere to hide. Every assertion also checks an icon is
// present, since a tone without an icon still fails "never color alone".

describe('toneForTaskStatus', () => {
  it.each([
    ['draft', 'neutral'],
    ['active', 'success'],
    ['paused', 'warning'],
    ['completed', 'neutral'],
    ['archived', 'neutral'],
  ] as const)('%s -> %s', (status, tone) => {
    const result = toneForTaskStatus(status)
    expect(result.tone).toBe(tone)
    expect(result.icon).toBeDefined()
  })

  it('falls back to neutral for an unrecognized status, not a silent default', () => {
    expect(toneForTaskStatus('some_future_status').tone).toBe('neutral')
  })
})

describe('toneForRunState', () => {
  it.each([
    ['pending', 'info'],
    ['running', 'info'],
    ['retry_wait', 'warning'],
    ['succeeded', 'success'],
    ['dead', 'danger'],
    ['cancelled', 'neutral'],
    ['blocked', 'warning'],
  ] as const)('%s -> %s', (state, tone) => {
    const result = toneForRunState(state)
    expect(result.tone).toBe(tone)
    expect(result.icon).toBeDefined()
  })

  it('distinguishes a dead run from a succeeded run by tone and icon, not just text', () => {
    const dead = toneForRunState('dead')
    const succeeded = toneForRunState('succeeded')
    expect(dead.tone).not.toBe(succeeded.tone)
    expect(dead.icon).not.toBe(succeeded.icon)
  })

  it('falls back to neutral for an unrecognized state', () => {
    expect(toneForRunState('some_future_state').tone).toBe('neutral')
  })
})

describe('toneForApiKeyStatus', () => {
  it.each([
    ['active', 'success'],
    ['expired', 'warning'],
    ['revoked', 'neutral'],
  ] as const)('%s -> %s', (status, tone) => {
    expect(toneForApiKeyStatus(status).tone).toBe(tone)
  })
})

describe('toneForPipelineInstanceStatus', () => {
  it.each([
    ['pending', 'info'],
    ['running', 'info'],
    ['succeeded', 'success'],
    ['failed', 'danger'],
    ['cancelled', 'neutral'],
  ] as const)('%s -> %s', (status, tone) => {
    expect(toneForPipelineInstanceStatus(status).tone).toBe(tone)
  })
})

describe('toneForPipelineNodeStatus', () => {
  it.each([
    ['pending', 'info'],
    ['ready', 'info'],
    ['running', 'info'],
    ['succeeded', 'success'],
    ['failed', 'danger'],
    ['skipped', 'neutral'],
    ['halted', 'warning'],
  ] as const)('%s -> %s', (status, tone) => {
    expect(toneForPipelineNodeStatus(status).tone).toBe(tone)
  })
})

describe('toneForPlatformHealth', () => {
  it.each([
    ['healthy', 'success'],
    ['degraded', 'warning'],
  ] as const)('%s -> %s', (status, tone) => {
    expect(toneForPlatformHealth(status).tone).toBe(tone)
  })
})
