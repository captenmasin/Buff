<script setup lang="ts">
import { computed } from 'vue';
import { calorieRemainingAriaLabel, calorieRemainingLabel, calorieRingStrokeClass } from '../calorieRing';

const props = withDefaults(defineProps<{
    consumed: number;
    goal?: number;
    remaining?: number;
    burned?: number;
}>(), {
    goal: 0,
    remaining: 0,
    burned: 0,
});

const radius = 46;
const circumference = 2 * Math.PI * radius;
const progress = computed(() => {
    if (!props.goal) {
        return 0;
    }

    return Math.min(1, Math.max(0, props.consumed / props.goal));
});
const dashOffset = computed(() => circumference * (1 - progress.value));
const strokeClass = computed(() => calorieRingStrokeClass(props.consumed, props.goal));
const remainingLabel = computed(() => calorieRemainingLabel(props.remaining, props.goal));
const ariaLabel = computed(() => calorieRemainingAriaLabel(props.consumed, props.remaining, props.goal));
</script>

<template>
    <div
        class="flex items-center gap-4"
        role="img"
        :aria-label="ariaLabel"
    >
        <div class="relative grid size-28 shrink-0 place-items-center">
            <svg viewBox="0 0 112 112" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="56" cy="56" :r="radius" fill="none" class="stroke-muted" stroke-width="8" />
                <circle
                    cx="56"
                    cy="56"
                    :r="radius"
                    fill="none"
                    class="transition-[stroke-dashoffset,stroke] duration-300 ease-out motion-reduce:transition-none"
                    :class="strokeClass"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="dashOffset"
                />
            </svg>
            <div class="absolute inset-0 grid place-items-center text-center">
                <p class="text-display font-bold leading-none tracking-tight tabular-nums">{{ consumed }}</p>
            </div>
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-semibold tracking-tight">Calories</p>
            <p class="mt-1 text-sm text-muted-foreground">{{ remainingLabel }}</p>
            <p v-if="burned" class="mt-1 text-sm text-muted-foreground">{{ burned }} burned</p>
        </div>
    </div>
</template>
