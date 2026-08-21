<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { dayStatusClass, dayStatusHasTick, type DayStatus } from '../dayStatus';

const props = withDefaults(defineProps<{
    status: DayStatus;
    size?: number;
}>(), {
    size: 16,
});

const tickSize = computed(() => Math.round(props.size * 0.62));
</script>

<template>
    <span
        class="inline-grid shrink-0 place-items-center rounded-full"
        :class="dayStatusClass(status)"
        :style="{ width: `${size}px`, height: `${size}px` }"
        aria-hidden="true"
    >
        <Check
            v-if="dayStatusHasTick(status)"
            :size="tickSize"
            stroke-width="3"
        />
    </span>
</template>
