import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import axe from 'axe-core'
import { Info } from 'lucide-vue-next'

import BaseBadge from './BaseBadge.vue'
import BaseButton from './BaseButton.vue'
import BaseCard from './BaseCard.vue'
import BaseInput from './BaseInput.vue'
import BaseSelect from './BaseSelect.vue'
import BaseTextarea from './BaseTextarea.vue'
import CodeBlock from './CodeBlock.vue'
import EmptyState from './EmptyState.vue'
import FormField from './FormField.vue'

// A composed page exercising every primitive together, so a labeling or
// contrast regression in one shows up here rather than only after a real
// page migration (#91-#94) starts using them. Mirrors the pattern in
// login.a11y.spec.ts.
const CompositionHost = defineComponent({
  components: {
    BaseBadge,
    BaseButton,
    BaseCard,
    BaseInput,
    BaseSelect,
    BaseTextarea,
    CodeBlock,
    EmptyState,
    FormField,
  },
  data() {
    return { infoIcon: Info }
  },
  template: `
    <div>
      <BaseButton variant="primary">Save</BaseButton>
      <BaseButton variant="secondary">Cancel</BaseButton>
      <BaseButton variant="tertiary">Learn more</BaseButton>
      <BaseButton variant="danger">Delete</BaseButton>
      <BaseButton loading>Submitting</BaseButton>

      <BaseCard>
        <FormField id="email" label="Email" hint="Used for sign-in" v-slot="{ describedBy, ariaInvalid }">
          <BaseInput id="email" :described-by="describedBy" :aria-invalid="ariaInvalid" />
        </FormField>
        <FormField id="reason" label="Reason" error="Required" v-slot="{ describedBy, ariaInvalid }">
          <BaseTextarea id="reason" :described-by="describedBy" :aria-invalid="ariaInvalid" />
        </FormField>
        <FormField id="tenant" label="Tenant" v-slot="{ describedBy, ariaInvalid }">
          <BaseSelect id="tenant" :described-by="describedBy" :aria-invalid="ariaInvalid">
            <option value="a">Tenant A</option>
          </BaseSelect>
        </FormField>
      </BaseCard>

      <BaseBadge label="Active" />
      <BaseBadge label="Failed" tone="danger" />
      <BaseBadge label="Info" :icon="infoIcon" />

      <CodeBlock label="Response body">{"ok":true}</CodeBlock>

      <EmptyState message="No tasks yet." />
    </div>
  `,
})

describe('primitives composition a11y', () => {
  it('has no serious or critical axe violations', async () => {
    const wrapper = mount(CompositionHost, { attachTo: document.body })

    const results = await axe.run(wrapper.element, {
      rules: {
        // jsdom lacks full color contrast computation reliability, same as login.a11y.spec.ts.
        'color-contrast': { enabled: false },
      },
    })

    const serious = results.violations.filter((v) => ['serious', 'critical'].includes(v.impact ?? ''))
    expect(serious, JSON.stringify(serious, null, 2)).toEqual([])
    wrapper.unmount()
  })
})
