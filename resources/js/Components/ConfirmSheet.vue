<script setup lang="ts">
import AppSheet from './AppSheet.vue';
import Button from './ui/button/Button.vue';

withDefaults(defineProps<{
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
}>(), {
    confirmLabel: 'Delete',
});

const emit = defineEmits<{
    cancel: [];
    confirm: [];
}>();
</script>

<template>
    <AppSheet :open="open" labelled-by="confirm-sheet-title" @close="emit('cancel')">
        <h2 id="confirm-sheet-title" class="text-xl font-semibold tracking-tight">{{ title }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">{{ message }}</p>
        <div class="mt-4 grid grid-cols-2 gap-2">
            <Button type="button" variant="surface" @click="emit('cancel')">Cancel</Button>
            <Button type="button" variant="destructive" @click="emit('confirm')">{{ confirmLabel }}</Button>
        </div>
    </AppSheet>
</template>
