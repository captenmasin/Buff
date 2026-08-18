<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, provide, ref, shallowRef, useAttrs } from 'vue';
import { cn } from '../../../utils';
import { SELECT_CONTEXT_KEY, type SelectItemRegistration } from './selectContext';

defineOptions({
    inheritAttrs: false,
});

const model = defineModel<string | number | null | undefined>({
    default: undefined,
});

const props = withDefaults(defineProps<{
    class?: string;
    disabled?: boolean;
}>(), {
    class: '',
    disabled: false,
});

const attrs = useAttrs();
const root = shallowRef<HTMLElement | null>(null);
const open = ref(false);
const items = ref<SelectItemRegistration[]>([]);
const highlightedValue = ref<string | null>(null);

const disabled = computed(() => props.disabled || (attrs.disabled !== undefined && attrs.disabled !== false));

function registerItem(item: SelectItemRegistration): void {
    const existingIndex = items.value.findIndex((entry) => entry.value === item.value);

    if (existingIndex >= 0) {
        items.value[existingIndex] = item;

        return;
    }

    items.value.push(item);
}

function unregisterItem(value: string): void {
    items.value = items.value.filter((entry) => entry.value !== value);
}

function selectItem(value: string): void {
    model.value = value;
    open.value = false;
    highlightedValue.value = null;
}

function close(): void {
    open.value = false;
    highlightedValue.value = null;
}

function toggle(): void {
    if (disabled.value) {
        return;
    }

    open.value = !open.value;

    if (open.value) {
        highlightedValue.value = model.value === null || model.value === undefined
            ? items.value.find((entry) => !entry.disabled)?.value ?? null
            : String(model.value);
    }
}

function handleDocumentClick(event: MouseEvent): void {
    if (!open.value || !(event.target instanceof Node) || root.value?.contains(event.target)) {
        return;
    }

    close();
}

function handleKeydown(event: KeyboardEvent): void {
    if (!open.value) {
        return;
    }

    const enabledItems = items.value.filter((entry) => !entry.disabled);

    if (enabledItems.length === 0) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();

        return;
    }

    const currentIndex = enabledItems.findIndex((entry) => entry.value === highlightedValue.value);

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        const nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % enabledItems.length;
        highlightedValue.value = enabledItems[nextIndex].value;

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        const nextIndex = currentIndex <= 0 ? enabledItems.length - 1 : currentIndex - 1;
        highlightedValue.value = enabledItems[nextIndex].value;

        return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();

        if (highlightedValue.value) {
            selectItem(highlightedValue.value);
        }
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
});

provide(SELECT_CONTEXT_KEY, {
    root,
    model,
    open,
    disabled,
    items,
    highlightedValue,
    registerItem,
    unregisterItem,
    selectItem,
    close,
    toggle,
});
</script>

<template>
    <div ref="root" :class="cn('relative w-full', props.class)">
        <slot />
    </div>
</template>
