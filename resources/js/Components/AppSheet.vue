<script setup lang="ts">
import { ref, watch } from 'vue';
import Card from './Card.vue';
import { cn } from '../utils';
import { useFocusTrap } from '../useFocusTrap';

const props = withDefaults(defineProps<{
    open: boolean;
    labelledBy: string;
    variant?: 'modal' | 'drawer';
    class?: string;
}>(), {
    variant: 'modal',
    class: '',
});

const emit = defineEmits<{
    close: [];
}>();

const panel = ref<HTMLElement | null>(null);
const { onKeydown, focusFirst } = useFocusTrap(panel, () => emit('close'));

watch(() => props.open, (open) => {
    if (open) {
        focusFirst();
    }
});
</script>

<template>
    <div
        v-if="open"
        class="sheet-scrim"
        :class="variant === 'drawer' ? 'sheet-scrim-drawer' : ''"
        @click.self="emit('close')"
        @keydown="onKeydown"
    >
        <div
            ref="panel"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="labelledBy"
            tabindex="-1"
            :class="cn(
                variant === 'drawer'
                    ? 'bottom-drawer relative w-full max-w-md overflow-y-auto rounded-t-3xl border border-border/70 bg-card px-4 pt-3 shadow-card sm:max-w-lg'
                    : 'w-full max-w-md sm:max-w-lg',
                props.class,
            )"
        >
            <Card v-if="variant === 'modal'" class="overflow-hidden">
                <slot />
            </Card>
            <slot v-else />
        </div>
    </div>
</template>
