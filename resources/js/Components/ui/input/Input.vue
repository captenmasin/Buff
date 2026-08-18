<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { cn } from '../../../utils';

defineOptions({
    inheritAttrs: false,
});

const [model, modifiers] = defineModel<string | number | null | undefined>({
    default: undefined,
});

const props = withDefaults(defineProps<{
    class?: string;
}>(), {
    class: '',
});

const attrs = useAttrs();
const inputValue = computed<string | number>(() => String(model.value ?? attrs.value ?? ''));

function updateValue(event: Event): void {
    const target = event.target instanceof HTMLInputElement ? event.target : null;

    if (!target) {
        return;
    }

    model.value = modifiers.number && target.value !== '' ? Number(target.value) : target.value;
}
</script>

<template>
    <input
        v-bind="attrs"
        :value="inputValue"
        :class="cn(
            'w-full rounded-xl border border-border/80 bg-muted/70 px-3.5 py-3 text-base outline-none transition placeholder:text-muted-foreground/70 focus:border-ring focus:bg-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60',
            props.class,
        )"
        @input="updateValue"
    >
</template>
