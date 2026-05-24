<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../../utils';

type ButtonVariant = 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive' | 'inverse' | 'surface';
type ButtonSize = 'default' | 'sm' | 'lg' | 'icon' | 'nav';

const props = defineProps<{
    as?: any;
    variant?: ButtonVariant;
    size?: ButtonSize;
    class?: string;
}>();

const component = computed(() => props.as || 'button');

const variants: Record<ButtonVariant, string> = {
    default: 'bg-primary text-primary-foreground shadow-sm active:bg-foreground disabled:bg-muted-foreground/35 disabled:text-primary-foreground',
    secondary: 'bg-secondary text-secondary-foreground active:bg-secondary/80',
    outline: 'border border-border bg-card text-foreground/80 shadow-sm active:bg-muted',
    ghost: 'text-muted-foreground active:bg-muted',
    destructive: 'text-destructive active:bg-danger-soft',
    inverse: 'bg-card text-foreground active:bg-muted',
    surface: 'border border-border bg-muted text-foreground active:bg-muted',
};

const sizes: Record<ButtonSize, string> = {
    default: 'h-12 px-4 py-3',
    sm: 'h-8 px-3 text-sm',
    lg: 'h-14 px-4 py-4 text-base',
    icon: 'grid h-10 w-10 place-items-center p-0',
    nav: 'min-h-14 flex-col gap-1 px-2 py-2 text-[11px]',
};
</script>

<template>
    <component
        :is="component"
        :class="cn(
            'inline-flex items-center justify-center gap-2 rounded-md transition disabled:cursor-not-allowed disabled:opacity-60',
            variants[props.variant || 'default'],
            sizes[props.size || 'default'],
            props.class,
        )"
    >
        <slot />
    </component>
</template>
