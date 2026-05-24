<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { cn } from '../../../utils';

const props = withDefaults(defineProps<{
    as?: string | Component;
    variant?: 'default' | 'destructive';
    class?: string;
}>(), {
    as: 'button',
    variant: 'default',
    class: '',
});

const component = computed(() => props.as);
</script>

<template>
    <component
        :is="component"
        type="button"
        role="menuitem"
        :class="cn(
            'flex w-full items-center gap-2 rounded-sm px-3 py-2 text-left text-sm font-medium outline-none transition active:bg-muted',
            props.variant === 'destructive' ? 'text-destructive active:bg-danger-soft' : 'text-foreground',
            props.class,
        )"
    >
        <slot />
    </component>
</template>
