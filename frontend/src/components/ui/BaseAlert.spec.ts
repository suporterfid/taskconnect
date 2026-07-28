import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import BaseAlert from './BaseAlert.vue'

describe('BaseAlert', () => {
  it('defaults to role=alert (assertive) with the info tone', () => {
    const wrapper = mount(BaseAlert, { slots: { default: 'Heads up' } })
    expect(wrapper.attributes('role')).toBe('alert')
    expect(wrapper.text()).toBe('Heads up')
  })

  it('accepts role=status for a polite, non-interrupting confirmation', () => {
    const wrapper = mount(BaseAlert, { props: { tone: 'success', role: 'status' } })
    expect(wrapper.attributes('role')).toBe('status')
  })

  it.each(['success', 'warning', 'danger', 'info', 'neutral'] as const)(
    'pairs the %s tone with an icon, never color alone',
    (tone) => {
      const wrapper = mount(BaseAlert, { props: { tone } })
      expect(wrapper.find('svg').exists()).toBe(true)
    },
  )
})
