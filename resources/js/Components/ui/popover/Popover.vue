<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { cn } from '../../../utils';

const props = withDefaults(defineProps<{
    align?: 'start' | 'end';
    class?: string;
    contentClass?: string;
}>(), {
    align: 'end',
    class: '',
    contentClass: '',
});

const open = defineModel<boolean>({ default: false });
const root = ref<HTMLElement | null>(null);

function close(): void {
    open.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
    if (!(event.target instanceof Node) || root.value?.contains(event.target)) {
        return;
    }

    close();
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
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
</script>

<template>
    <div ref="root" :class="cn('relative inline-flex', props.class)">
        <slot name="trigger" :open="open" :toggle="() => open = !open" :close="close" />

        <div
            v-if="open"
            :class="cn(
                'absolute top-full z-50 mt-2 rounded-md border border-border bg-card p-3 text-foreground shadow-md',
                props.align === 'end' ? 'right-0' : 'left-0',
                props.contentClass,
            )"
            data-slot="popover-content"
        >
            <slot :close="close" />
        </div>
    </div>
</template>
