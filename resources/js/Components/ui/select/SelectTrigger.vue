<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, inject, useAttrs } from 'vue';
import { cn } from '../../../utils';
import { SELECT_CONTEXT_KEY } from './selectContext';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(defineProps<{
    class?: string;
}>(), {
    class: '',
});

const attrs = useAttrs();
const select = inject(SELECT_CONTEXT_KEY);

if (!select) {
    throw new Error('SelectTrigger must be used within Select.');
}

const isDisabled = computed(() => select.disabled.value || (attrs.disabled !== undefined && attrs.disabled !== false));

function handleKeydown(event: KeyboardEvent): void {
    if (isDisabled.value) {
        return;
    }

    if ([' ', 'Enter', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();

        if (!select.open.value) {
            select.toggle();
        }
    }
}
</script>

<template>
    <button
        type="button"
        v-bind="attrs"
        :disabled="isDisabled"
        :aria-expanded="select.open.value"
        aria-haspopup="listbox"
        :class="cn(
            'flex w-full items-center justify-between gap-2 rounded-xl border border-border/80 bg-muted/70 px-3.5 py-3 text-left text-base outline-none transition focus:border-ring focus:bg-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60',
            props.class,
        )"
        @click="select.toggle()"
        @keydown="handleKeydown"
    >
        <span class="min-w-0 flex-1 truncate">
            <slot />
        </span>
        <ChevronDown
            :size="18"
            class="shrink-0 text-muted-foreground transition-transform duration-150"
            :class="select.open.value ? 'rotate-180' : ''"
        />
    </button>
</template>
