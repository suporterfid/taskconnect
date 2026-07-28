import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import EmptyState from './EmptyState.vue'

describe('EmptyState', () => {
  it('renders the message prop by default', () => {
    const wrapper = mount(EmptyState, { props: { message: 'No tasks yet.' } })
    expect(wrapper.text()).toBe('No tasks yet.')
  })

  it('lets the slot override the message', () => {
    const wrapper = mount(EmptyState, {
      props: { message: 'fallback' },
      slots: { default: 'Nothing here yet — create one to get started.' },
    })
    expect(wrapper.text()).toBe('Nothing here yet — create one to get started.')
  })
})
