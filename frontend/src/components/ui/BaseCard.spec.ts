import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import BaseCard from './BaseCard.vue'

describe('BaseCard', () => {
  it('renders its slot content', () => {
    const wrapper = mount(BaseCard, { slots: { default: 'Card body' } })
    expect(wrapper.text()).toBe('Card body')
  })

  it('uses the surface-emphasis tone plus a stronger border when selected', () => {
    const wrapper = mount(BaseCard, { props: { selected: true } })
    expect(wrapper.classes()).toEqual(expect.arrayContaining(['bg-[var(--color-bg-selected)]', 'border-border-strong']))
  })

  it('defaults to the plain surface tone', () => {
    const wrapper = mount(BaseCard)
    expect(wrapper.classes()).toEqual(expect.arrayContaining(['bg-[var(--color-bg-elevated)]', 'border-border']))
  })
})
