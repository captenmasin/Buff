<script setup lang="ts">
import { cn } from '../lib/utils'

const props = defineProps({
    class: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <div
        data-slot="card"
        :class="cn('group/card flex flex-col gap-0 overflow-hidden rounded-2xl bg-card px-5 py-5 text-sm text-card-foreground shadow-card has-data-[slot=card-footer]:pb-0 has-[>img:first-child]:pt-0 *:[img:first-child]:rounded-t-2xl *:[img:last-child]:rounded-b-2xl', props.class)"
    >
        <slot />
    </div>
</template>
