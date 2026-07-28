import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { Info } from 'lucide-vue-next'

import AppIcon from './AppIcon.vue'

describe('AppIcon', () => {
  it('is decorative and hidden from assistive technology by default', () => {
    const wrapper = mount(AppIcon, { props: { icon: Info } })
    const svg = wrapper.find('svg')

    expect(svg.attributes('aria-hidden')).toBe('true')
    expect(svg.attributes('role')).toBeUndefined()
    expect(svg.attributes('aria-label')).toBeUndefined()
  })

  it('exposes an accessible name instead of aria-hidden when label is set', () => {
    const wrapper = mount(AppIcon, { props: { icon: Info, label: 'Info' } })
    const svg = wrapper.find('svg')

    expect(svg.attributes('aria-hidden')).toBeUndefined()
    expect(svg.attributes('role')).toBe('img')
    expect(svg.attributes('aria-label')).toBe('Info')
  })

  it('defaults to size 20 and inherits currentColor', () => {
    const wrapper = mount(AppIcon, { props: { icon: Info } })
    const svg = wrapper.find('svg')

    expect(svg.attributes('width')).toBe('20')
    expect(svg.attributes('height')).toBe('20')
    expect(svg.attributes('stroke')).toBe('currentColor')
  })

  it('accepts the 16/24px scale from the spec', () => {
    const wrapper = mount(AppIcon, { props: { icon: Info, size: 16 } })
    expect(wrapper.find('svg').attributes('width')).toBe('16')
  })
})
