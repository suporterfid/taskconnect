import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import BaseButton from './BaseButton.vue'

describe('BaseButton', () => {
  it.each([
    ['primary', 'bg-[var(--color-action-primary)]'],
    ['secondary', 'border-border-strong'],
    ['tertiary', 'text-action-text'],
    ['danger', 'status-danger'],
  ] as const)('renders the %s variant', (variant, expectedClass) => {
    const wrapper = mount(BaseButton, { props: { variant }, slots: { default: 'Save' } })
    expect(wrapper.text()).toBe('Save')
    expect(wrapper.get('button').classes()).toContain(expectedClass)
  })

  it('meets the 44px minimum touch target at the default md size', () => {
    const wrapper = mount(BaseButton, { slots: { default: 'Save' } })
    expect(wrapper.get('button').classes()).toEqual(expect.arrayContaining(['min-h-11', 'min-w-11']))
  })

  it('preserves a 44px target for visually compact table actions', () => {
    const wrapper = mount(BaseButton, { props: { size: 'sm' }, slots: { default: 'Edit' } })
    expect(wrapper.get('button').classes()).toEqual(expect.arrayContaining(['min-h-11', 'min-w-11']))
  })

  it('forwards disabled as a real disabled attribute, not just a style', () => {
    const wrapper = mount(BaseButton, { props: { disabled: true }, slots: { default: 'Save' } })
    const button = wrapper.get('button')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-disabled')).toBe('true')
  })

  it('marks aria-busy and shows a spinner while loading, without losing the label', () => {
    const wrapper = mount(BaseButton, { props: { loading: true }, slots: { default: 'Save' } })
    const button = wrapper.get('button')
    expect(button.attributes('aria-busy')).toBe('true')
    expect(button.attributes('disabled')).toBeDefined()
    expect(wrapper.find('svg').exists()).toBe(true)
    expect(wrapper.text()).toContain('Save')
  })
})
