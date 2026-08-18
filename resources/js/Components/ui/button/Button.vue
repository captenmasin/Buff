<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '../../../utils';

defineOptions({
    inheritAttrs: false,
});

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
    outline: 'border border-border bg-card text-foreground/80 active:bg-muted',
    ghost: 'text-muted-foreground active:bg-muted',
    destructive: 'text-destructive active:bg-danger-soft',
    inverse: 'bg-card text-foreground active:bg-muted',
    surface: 'border border-border/80 bg-muted/80 text-foreground active:bg-muted',
};

const sizes: Record<ButtonSize, string> = {
    default: 'h-12 px-4 py-3',
    sm: 'h-9 px-3 text-sm',
    lg: 'h-14 px-4 py-4 text-base',
    icon: 'grid h-11 w-11 place-items-center p-0',
    nav: 'min-h-12 flex-col gap-0.5 px-1 py-1.5 text-xs font-semibold tracking-wide',
};
</script>

<template>
    <component
        :is="component"
        v-bind="$attrs"
        :class="cn(
            'inline-flex items-center justify-center gap-2 rounded-xl font-medium transition-[transform,background-color,opacity] duration-150 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background motion-reduce:transition-none motion-reduce:active:scale-100 disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100',
            variants[props.variant || 'default'],
            sizes[props.size || 'default'],
            props.class,
        )"
    >
        <slot />
    </component>
</template>
