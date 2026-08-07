import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { mount } from '@vue/test-utils'
import { ChevronLeft, Info, Settings } from 'lucide-vue-next'
import { describe, expect, it } from 'vitest'

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

  it('marks only explicitly directional icons for RTL mirroring', () => {
    const directional = mount(AppIcon, {
      props: { icon: ChevronLeft, directional: true },
    })
    const neutral = mount(AppIcon, {
      props: { icon: Settings },
    })

    expect(directional.get('svg').classes()).toContain('app-icon--directional')
    expect(neutral.get('svg').classes()).not.toContain('app-icon--directional')
  })

  it('scopes RTL mirroring to directional icons without transforming the root element', () => {
    const source = readFileSync(resolve(import.meta.dirname, 'AppIcon.vue'), 'utf8')
    expect(source).toContain(":global([dir='rtl'] .app-icon--directional)")
    expect(source).not.toContain(":global([dir='rtl']) .app-icon--directional")
  })
})
