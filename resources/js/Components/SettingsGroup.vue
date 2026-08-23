<script setup lang="ts">
defineProps<{
    title?: string;
}>();
</script>

<template>
    <section>
        <h2 v-if="title" class="px-5 pb-2 text-[0.8125rem] font-medium text-muted-foreground">{{ title }}</h2>
        <div class="divide-y divide-border/60 overflow-hidden rounded-2xl bg-card shadow-card">
            <slot />
        </div>
    </section>
</template>
