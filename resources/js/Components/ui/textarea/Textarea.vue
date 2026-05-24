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
const textareaValue = computed<string | number>(() => String(model.value ?? attrs.value ?? ''));

function updateValue(event: Event): void {
    const target = event.target instanceof HTMLTextAreaElement ? event.target : null;

    if (target) {
        model.value = target.value;
    }
}
</script>

<template>
    <textarea
        v-bind="attrs"
        :value="textareaValue"
        :class="cn(
            'w-full resize-none rounded-md border border-border bg-muted px-3 py-3 text-base outline-none transition placeholder:text-muted-foreground/70 focus:border-ring disabled:cursor-not-allowed disabled:opacity-60',
            props.class,
        )"
        @input="updateValue"
    />
</template>
