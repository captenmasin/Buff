<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

const model = defineModel<string | number | null | undefined>({
  default: undefined,
})

const props = withDefaults(defineProps<{
  class?: HTMLAttributes['class']
}>(), {
  class: '',
})

const attrs = useAttrs()
const textareaValue = computed<string | number>(() => String(model.value ?? attrs.value ?? ''))

function updateValue(event: Event): void {
  const target = event.target instanceof HTMLTextAreaElement ? event.target : null

  if (target) {
    model.value = target.value
  }
}
</script>

<template>
  <textarea
    v-bind="attrs"
    data-slot="textarea"
    :value="textareaValue"
    :class="cn(
      'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:aria-invalid:border-destructive/50 disabled:bg-input/50 dark:disabled:bg-input/80 min-h-16 w-full rounded-lg border bg-card px-3.5 py-3 text-base transition-colors aria-invalid:ring-3 field-sizing-content outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
    @input="updateValue"
  />
</template>
