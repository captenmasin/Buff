<script setup lang="ts">
import { cn } from '../../../utils';

type BadgeVariant = 'default' | 'success' | 'destructive' | 'muted';

const props = withDefaults(defineProps<{
    variant?: BadgeVariant;
    class?: string;
}>(), {
    variant: 'default',
    class: '',
});

const variants: Record<BadgeVariant, string> = {
    default: 'bg-secondary text-foreground',
    success: 'bg-success/10 text-success-foreground',
    destructive: 'bg-danger-soft text-danger-soft-foreground',
    muted: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <span :class="cn('inline-flex items-center rounded-md px-3 py-2 text-xs font-semibold', variants[variant], props.class)">
        <slot />
    </span>
</template>
