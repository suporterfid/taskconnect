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

  it('preserves CJK composition and emits only the committed value', async () => {
    const wrapper = mount(BaseInput, { props: { modelValue: '' } })
    const input = wrapper.get('input')
    await input.trigger('compositionstart')
    ;(input.element as HTMLInputElement).value = 'に'
    await input.trigger('input')
    ;(input.element as HTMLInputElement).value = '日本'
    await input.trigger('input')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    await input.trigger('compositionend')
    expect(wrapper.emitted('update:modelValue')).toEqual([['日本']])
  })

  it.each(['مرحبا', 'สวัสดี', 'नमस्ते'])('emits pasted script text immediately: %s', async (value) => {
    const wrapper = mount(BaseInput)
    const input = wrapper.get('input')
    ;(input.element as HTMLInputElement).value = value
    await input.trigger('input')
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([value])
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

  it('preserves composition and emits the final value once', async () => {
    const wrapper = mount(BaseTextarea)
    const textarea = wrapper.get('textarea')
    await textarea.trigger('compositionstart')
    ;(textarea.element as HTMLTextAreaElement).value = '漢'
    await textarea.trigger('input')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    ;(textarea.element as HTMLTextAreaElement).value = '漢字'
    await textarea.trigger('compositionend')
    expect(wrapper.emitted('update:modelValue')).toEqual([['漢字']])
  })
})
