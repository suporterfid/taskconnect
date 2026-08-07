import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import BaseInput from './BaseInput.vue'
import BaseSelect from './BaseSelect.vue'
import BaseTextarea from './BaseTextarea.vue'

describe('BaseInput', () => {
  it('emits update:modelValue on input', async () => {
    const wrapper = mount(BaseInput, { props: { modelValue: '' } })
    await wrapper.get('input').setValue('hello@example.com')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['hello@example.com'])
  })

  it('adds a non-color invalid cue while retaining the strong boundary', () => {
    const wrapper = mount(BaseInput, { props: { ariaInvalid: 'true' } })
    expect(wrapper.get('input').classes()).toEqual(
      expect.arrayContaining(['border-border-strong', 'invalid-control']),
    )
  })

  it('defaults to the strong control boundary', () => {
    const wrapper = mount(BaseInput)
    expect(wrapper.get('input').classes()).toContain('border-border-strong')
  })
})

describe('BaseSelect', () => {
  it('emits update:modelValue on change', async () => {
    const wrapper = mount(BaseSelect, {
      slots: { default: '<option value="a">A</option><option value="b">B</option>' },
    })
    await wrapper.get('select').setValue('b')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['b'])
  })
})

describe('BaseTextarea', () => {
  it('emits update:modelValue on input', async () => {
    const wrapper = mount(BaseTextarea)
    await wrapper.get('textarea').setValue('multi\nline')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['multi\nline'])
  })

  it('defaults to 3 rows', () => {
    const wrapper = mount(BaseTextarea)
    expect(wrapper.get('textarea').attributes('rows')).toBe('3')
  })
})
