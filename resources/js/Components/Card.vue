<script setup lang="ts">
import { cn } from '../utils';

const props = defineProps({
    class: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <article :class="cn('rounded-xl border border-border/80 bg-card p-5 text-foreground shadow-card', props.class)">
        <slot />
    </article>
</template>
