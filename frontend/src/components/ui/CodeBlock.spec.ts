import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import CodeBlock from './CodeBlock.vue'

describe('CodeBlock', () => {
  it('renders slot content inside pre > code', () => {
    const wrapper = mount(CodeBlock, { slots: { default: '{"ok":true}' } })
    expect(wrapper.get('pre code').text()).toBe('{"ok":true}')
  })

  it('uses the mono font token', () => {
    const wrapper = mount(CodeBlock, { slots: { default: '{}' } })
    expect(wrapper.get('pre').classes()).toContain('font-mono')
  })

  it('renders an optional label as a caption', () => {
    const wrapper = mount(CodeBlock, { props: { label: 'Response body' }, slots: { default: '{}' } })
    expect(wrapper.get('figcaption').text()).toBe('Response body')
  })
})
