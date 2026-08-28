<script setup lang="ts">
import type { SelectItemProps } from 'reka-ui'

import type { HTMLAttributes } from 'vue'
import { CheckIcon } from '@lucide/vue'
import { reactiveOmit } from '@vueuse/core'
import {
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  useForwardProps,
} from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<SelectItemProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <SelectItem
    data-slot="select-item"
    v-bind="forwardedProps"
    :class="
      cn(
        'focus:bg-muted focus:text-foreground not-data-[variant=destructive]:focus:**:text-foreground gap-0.5 rounded-md py-2 pr-8 pl-2.5 text-sm [&_svg:not([class*=size-])]:size-4 relative flex w-full cursor-default outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
        $slots.description ? 'flex-col items-start' : 'items-center',
        props.class,
      )
    "
  >
    <span
      class="pointer-events-none absolute right-2 flex size-4 items-center justify-center"
      :class="$slots.description ? 'top-2' : ''"
    >
      <SelectItemIndicator>
        <slot name="indicator-icon">
          <CheckIcon class="pointer-events-none" />
        </slot>
      </SelectItemIndicator>
    </span>

    <SelectItemText>
      <slot />
    </SelectItemText>
    <span v-if="$slots.description" class="text-xs text-muted-foreground">
      <slot name="description" />
    </span>
  </SelectItem>
</template>
