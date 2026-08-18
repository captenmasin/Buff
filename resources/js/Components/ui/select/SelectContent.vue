<script setup lang="ts">
import { inject } from 'vue';
import { cn } from '../../../utils';
import { SELECT_CONTEXT_KEY } from './selectContext';

const props = withDefaults(defineProps<{
    class?: string;
}>(), {
    class: '',
});

const select = inject(SELECT_CONTEXT_KEY);

if (!select) {
    throw new Error('SelectContent must be used within Select.');
}
</script>

<template>
    <div
        v-show="select.open.value"
        role="listbox"
        tabindex="-1"
        data-slot="select-content"
        :class="cn(
            'absolute left-0 right-0 top-full z-50 mt-2 max-h-60 overflow-y-auto rounded-xl border border-border/80 bg-card p-1 text-foreground shadow-card',
            props.class,
        )"
    >
        <slot />
    </div>
</template>
