<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { cn } from '../../../utils';

defineOptions({
    inheritAttrs: false,
});

const model = defineModel<string | number | null | undefined>({
    default: undefined,
});

const props = withDefaults(defineProps<{
    class?: string;
}>(), {
    class: '',
});

const attrs = useAttrs();
const selectValue = computed<string | number>(() => String(model.value ?? attrs.value ?? ''));

function updateValue(event: Event): void {
    const target = event.target instanceof HTMLSelectElement ? event.target : null;

    if (target) {
        model.value = target.value;
    }
}
</script>

<template>
    <select
        v-bind="attrs"
        :value="selectValue"
        :class="cn(
            'w-full rounded-md border border-border bg-muted px-3 py-3 text-base outline-none transition focus:border-ring disabled:cursor-not-allowed disabled:opacity-60',
            props.class,
        )"
        @change="updateValue"
    >
        <slot />
    </select>
</template>
