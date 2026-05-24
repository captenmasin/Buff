<script setup lang="ts">
import { cn } from '../../../utils';

const model = defineModel<boolean>({ required: true });
const emit = defineEmits<{
    change: [];
}>();

const props = withDefaults(defineProps<{
    class?: string;
}>(), {
    class: '',
});
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :class="cn(
            'relative inline-flex h-7 w-12 items-center rounded-full transition focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
            model ? 'bg-primary' : 'bg-muted-foreground/35',
            props.class,
        )"
        @click="model = !model; emit('change')"
    >
        <span
            class="h-5 w-5 rounded-full bg-card transition"
            :class="model ? 'translate-x-6' : 'translate-x-1'"
        />
    </button>
</template>
