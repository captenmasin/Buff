<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, onUpdated, ref, watch } from 'vue';
import { cn } from '../../../utils';
import { SELECT_CONTEXT_KEY } from './selectContext';

const props = withDefaults(defineProps<{
    value: string | number;
    disabled?: boolean;
    class?: string;
}>(), {
    disabled: false,
    class: '',
});

const select = inject(SELECT_CONTEXT_KEY);

if (!select) {
    throw new Error('SelectItem must be used within Select.');
}

const itemRef = ref<HTMLElement | null>(null);
const stringValue = computed(() => String(props.value));
const isSelected = computed(() => String(select.model.value ?? '') === stringValue.value);
const isHighlighted = computed(() => select.highlightedValue.value === stringValue.value);

function syncRegistration(): void {
    select.registerItem({
        value: stringValue.value,
        label: itemRef.value?.textContent?.trim() || stringValue.value,
        disabled: props.disabled,
    });
}

function handleSelect(): void {
    if (props.disabled) {
        return;
    }

    select.selectItem(stringValue.value);
}

onMounted(() => {
    syncRegistration();
});

onUpdated(() => {
    syncRegistration();
});

onBeforeUnmount(() => {
    select.unregisterItem(stringValue.value);
});

watch(stringValue, (value, previousValue) => {
    if (previousValue !== undefined) {
        select.unregisterItem(previousValue);
    }

    syncRegistration();
});

watch(() => props.disabled, syncRegistration);
</script>

<template>
    <div
        ref="itemRef"
        role="option"
        :aria-selected="isSelected"
        :aria-disabled="props.disabled"
        :data-value="stringValue"
        :class="cn(
            'cursor-pointer rounded-lg px-3 py-2.5 text-base outline-none transition',
            isSelected ? 'bg-secondary text-secondary-foreground' : 'text-foreground',
            isHighlighted && !isSelected ? 'bg-muted' : '',
            props.disabled ? 'cursor-not-allowed opacity-50' : 'active:bg-muted',
            props.class,
        )"
        @click="handleSelect"
        @mouseenter="select.highlightedValue.value = stringValue"
    >
        <slot />
    </div>
</template>
