<script setup lang="ts">
import { Check, Minus, Plus } from '@lucide/vue';
import { computed } from 'vue';
import { dayStatusClass, dayStatusIcon, type DayStatus } from '../dayStatus';

const props = withDefaults(defineProps<{
    status: DayStatus;
    size?: number;
}>(), {
    size: 16,
});

const glyph = computed(() => dayStatusIcon(props.status));
const glyphSize = computed(() => Math.round(props.size * 0.62));
</script>

<template>
    <span
        class="inline-grid shrink-0 place-items-center rounded-full"
        :class="dayStatusClass(status)"
        :style="{ width: `${size}px`, height: `${size}px` }"
        aria-hidden="true"
    >
        <Check
            v-if="glyph === 'check'"
            :size="glyphSize"
            stroke-width="3"
        />
        <Minus
            v-else-if="glyph === 'minus'"
            :size="glyphSize"
            stroke-width="3"
        />
        <Plus
            v-else-if="glyph === 'plus'"
            :size="glyphSize"
            stroke-width="3"
        />
    </span>
</template>
