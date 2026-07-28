import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { Info } from 'lucide-vue-next'

import BaseBadge from './BaseBadge.vue'

describe('BaseBadge', () => {
  it('always renders a visible text label', () => {
    const wrapper = mount(BaseBadge, { props: { label: 'Active' } })
    expect(wrapper.text()).toBe('Active')
  })

  it('warns when mounted without the required label — color/icon alone is not a name', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    // @ts-expect-error — intentionally omitting the required prop to prove Vue catches it.
    mount(BaseBadge, { props: {} })
    const warnedAboutMissingLabel = warn.mock.calls.some((call) =>
      call.some((arg) => typeof arg === 'string' && arg.includes('Missing required prop')),
    )
    expect(warnedAboutMissingLabel).toBe(true)
    warn.mockRestore()
  })

  it('renders the danger tone', () => {
    const wrapper = mount(BaseBadge, { props: { label: 'Failed', tone: 'danger' } })
    expect(wrapper.classes()).toContain('text-danger')
  })

  it('treats its optional icon as decorative, since the label already carries the meaning', () => {
    const wrapper = mount(BaseBadge, { props: { label: 'Info', icon: Info } })
    expect(wrapper.get('svg').attributes('aria-hidden')).toBe('true')
  })
})
