<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';
import { cn } from '@/lib/utils';
import Input from './ui/input/Input.vue';

defineOptions({ inheritAttrs: false });

const model = defineModel<string>({ default: '' });
const props = withDefaults(defineProps<{
    class?: HTMLAttributes['class'];
    inputClass?: HTMLAttributes['class'];
    disabled?: boolean;
}>(), {
    class: '',
    inputClass: '',
    disabled: false,
});
const passwordVisible = ref(false);
</script>

<template>
    <span :class="cn('relative block', props.class)">
        <Input
            v-bind="$attrs"
            v-model="model"
            :type="passwordVisible ? 'text' : 'password'"
            :disabled="disabled"
            :class="cn('pr-12', props.inputClass)"
        />
        <button
            type="button"
            class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-lg text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
            :aria-label="passwordVisible ? 'Hide password' : 'Show password'"
            :disabled="disabled"
            @click="passwordVisible = !passwordVisible"
        >
            <EyeOff v-if="passwordVisible" :size="20" aria-hidden="true" />
            <Eye v-else :size="20" aria-hidden="true" />
        </button>
    </span>
</template>
