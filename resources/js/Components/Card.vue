<script setup lang="ts">
import { Card as UiCard } from './ui/card'
import { cn } from '../lib/utils'

const props = defineProps({
    class: {
        type: String,
        default: '',
    },
})
</script>

<template>
    <UiCard :class="cn('gap-0 px-5 py-5', props.class)">
        <slot />
    </UiCard>
</template>
