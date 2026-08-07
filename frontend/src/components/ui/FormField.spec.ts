import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import FormField from './FormField.vue'
import BaseInput from './BaseInput.vue'

describe('FormField', () => {
  it('associates the label with the field via for/id', () => {
    const wrapper = mount(FormField, { props: { id: 'email', label: 'Email' } })
    expect(wrapper.get('label').attributes('for')).toBe('email')
    expect(wrapper.text()).toContain('Email')
  })

  it('wires the hint into aria-describedby on the nested input', () => {
    const wrapper = mount(FormField, {
      props: { id: 'email', label: 'Email', hint: 'Used for sign-in' },
      slots: {
        default: `<template #default="{ describedBy }"><input :aria-describedby="describedBy" /></template>`,
      },
    })
    const hint = wrapper.find('#email-hint')
    expect(hint.text()).toBe('Used for sign-in')
    expect(wrapper.get('input').attributes('aria-describedby')).toBe('email-hint')
  })

  it('surfaces an error as role=alert, sets aria-invalid, and hides the hint', () => {
    const wrapper = mount(FormField, {
      props: { id: 'email', label: 'Email', hint: 'Used for sign-in', error: 'Required' },
      slots: {
        default: `<template #default="{ describedBy, ariaInvalid }"><input :aria-describedby="describedBy" :aria-invalid="ariaInvalid" /></template>`,
      },
    })
    expect(wrapper.find('#email-hint').exists()).toBe(false)
    const error = wrapper.get('[role="alert"]')
    expect(error.text()).toContain('Required')
    expect(wrapper.get('input').attributes('aria-describedby')).toBe('email-error')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })

  it('marks required fields with a visible, decorative asterisk', () => {
    const wrapper = mount(FormField, { props: { id: 'email', label: 'Email', required: true } })
    const asterisk = wrapper.get('span')
    expect(asterisk.attributes('aria-hidden')).toBe('true')
    expect(asterisk.text()).toBe('*')
  })

  it('composes with BaseInput end to end', () => {
    const wrapper = mount(FormField, {
      props: { id: 'email', label: 'Email', error: 'Required' },
      slots: {
        default: `<template #default="{ describedBy, ariaInvalid }">
          <BaseInput id="email" :described-by="describedBy" :aria-invalid="ariaInvalid" />
        </template>`,
      },
      global: { components: { BaseInput } },
    })
    const input = wrapper.get('input')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.classes()).toEqual(expect.arrayContaining(['border-border-strong', 'invalid-control']))
  })
})
