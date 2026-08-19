<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

const [model, modifiers] = defineModel<string | number | null | undefined>({
  default: undefined,
})

const props = withDefaults(defineProps<{
  class?: HTMLAttributes['class']
}>(), {
  class: '',
})

const attrs = useAttrs()
const inputValue = computed<string | number>(() => String(model.value ?? attrs.value ?? ''))

function updateValue(event: Event): void {
  const target = event.target instanceof HTMLInputElement ? event.target : null

  if (!target) {
    return
  }

  model.value = modifiers.number && target.value !== '' ? Number(target.value) : target.value
}
</script>

<template>
  <input
    v-bind="attrs"
    data-slot="input"
    :value="inputValue"
    :class="cn(
      'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:aria-invalid:border-destructive/50 disabled:bg-input/50 dark:disabled:bg-input/80 h-12 w-full min-w-0 rounded-lg border bg-card px-3.5 py-3 text-base transition-colors file:h-7 file:text-sm file:font-medium aria-invalid:ring-3 file:inline-flex file:border-0 file:bg-transparent file:text-foreground placeholder:text-muted-foreground outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
    @input="updateValue"
  >
</template>
