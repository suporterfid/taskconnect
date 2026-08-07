import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import PageHeader from './PageHeader.vue'

describe('PageHeader', () => {
  it('isolates machine and localized titles with automatic direction detection', () => {
    const wrapper = mount(PageHeader, {
      props: { title: 'run-مثال-123', subtitle: 'Run details' },
    })

    const title = wrapper.get('h1 bdi')
    expect(title.attributes('dir')).toBe('auto')
    expect(title.text()).toBe('run-مثال-123')
    expect(wrapper.get('p').text()).toBe('Run details')
  })
})
