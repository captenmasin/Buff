<script setup lang="ts">
withDefaults(defineProps<{
    kicker?: string;
}>(), {
    kicker: '',
});
</script>

<template>
    <header class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p v-if="kicker" class="page-kicker">{{ kicker }}</p>
            <h1 class="page-title">
                <slot />
            </h1>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
