<script setup lang="ts">
import type { PrimitiveProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import type { ButtonVariants } from '.'
import { LoaderCircle } from '@lucide/vue'
import { Primitive } from 'reka-ui'
import { cn } from '@/lib/utils'
import { buttonVariants } from '.'

interface Props extends PrimitiveProps {
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  class?: HTMLAttributes['class']
  disabled?: boolean
  loading?: boolean
  loadingLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  loadingLabel: 'Please wait…',
})
</script>

<template>
  <Primitive
    data-slot="button"
    :data-variant="variant"
    :data-size="size"
    :as="as ?? 'button'"
    :as-child="asChild"
    :class="cn(buttonVariants({ variant, size }), props.class)"
    :disabled="disabled || loading ? true : undefined"
    :aria-busy="loading || undefined"
  >
    <template v-if="loading">
      <LoaderCircle :size="18" class="animate-spin" aria-hidden="true" />
      <span>{{ loadingLabel }}</span>
    </template>
    <slot v-else />
  </Primitive>
</template>
