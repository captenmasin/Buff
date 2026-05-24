<script setup lang="ts">
import { cn } from '../utils';

const props = defineProps({
    class: {
        type: String,
        default: ""
    }
})
</script>

<template>
    <article :class="cn('rounded-md border border-border bg-card p-4 text-foreground', props.class)">
        <slot />
    </article>
</template>

<style scoped>

</style>
