<script setup lang="ts">
import { computed, inject } from 'vue';
import { cn } from '../../../utils';
import { SELECT_CONTEXT_KEY } from './selectContext';

const props = withDefaults(defineProps<{
    placeholder?: string;
    class?: string;
}>(), {
    placeholder: 'Select…',
    class: '',
});

const select = inject(SELECT_CONTEXT_KEY);

if (!select) {
    throw new Error('SelectValue must be used within Select.');
}

const selectedLabel = computed(() => {
    if (select.model.value === null || select.model.value === undefined || select.model.value === '') {
        return null;
    }

    const value = String(select.model.value);

    return select.items.value.find((entry) => entry.value === value)?.label ?? value;
});
</script>

<template>
    <span
        :class="cn(
            selectedLabel ? 'text-foreground' : 'text-muted-foreground/70',
            props.class,
        )"
    >
        {{ selectedLabel ?? placeholder }}
    </span>
</template>
