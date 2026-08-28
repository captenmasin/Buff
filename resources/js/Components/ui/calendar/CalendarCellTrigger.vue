<script lang="ts" setup>
import type { CalendarCellTriggerProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { CalendarCellTrigger, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/Components/ui/button'

const props = withDefaults(defineProps<CalendarCellTriggerProps & { class?: HTMLAttributes['class'] }>(), {
  as: 'button',
})

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <CalendarCellTrigger
    data-slot="calendar-cell-trigger"
    :class="cn(
      buttonVariants({ variant: 'ghost' }),
      'size-8 p-0 font-normal aria-selected:opacity-100 cursor-default',
      '[&[data-today]:not([data-selected])]:bg-muted [&[data-today]:not([data-selected])]:text-foreground',
      // Selected
      'data-[selected]:bg-primary-container data-[selected]:text-primary-container-foreground data-[selected]:opacity-100 data-[selected]:hover:bg-primary-container data-[selected]:hover:text-primary-container-foreground data-[selected]:focus:bg-primary-container data-[selected]:focus:text-primary-container-foreground dark:data-[selected]:bg-primary dark:data-[selected]:text-primary-foreground dark:data-[selected]:hover:bg-primary dark:data-[selected]:hover:text-primary-foreground dark:data-[selected]:focus:bg-primary dark:data-[selected]:focus:text-primary-foreground',
      // Disabled
      'data-[disabled]:text-muted-foreground data-[disabled]:opacity-50',
      // Unavailable
      'data-[unavailable]:text-destructive-foreground data-[unavailable]:line-through',
      // Outside months
      'data-[outside-view]:text-muted-foreground',
      props.class,
    )"
    v-bind="forwardedProps"
  >
    <slot />
  </CalendarCellTrigger>
</template>
