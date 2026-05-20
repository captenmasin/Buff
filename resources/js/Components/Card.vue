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
    <article :class="cn('rounded-md border border-stone-200 bg-white p-4', props.class)">
        <slot />
    </article>
</template>

<style scoped>

</style>
