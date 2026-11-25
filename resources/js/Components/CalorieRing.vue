<script setup lang="ts">
import { Flame } from '@lucide/vue';
import { computed } from 'vue';
import { calorieBudget } from '../calorieRing';

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

const budget = computed(() => calorieBudget(props.consumed, props.remaining, props.goal));
const calories = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 });
</script>

<template>
    <section class="flex min-w-0 flex-col rounded-2xl bg-brand-night p-4 text-brand-white shadow-card dark:border dark:border-border sm:p-5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-lg font-semibold tracking-tight">Calories</h2>
            <Flame :size="20" class="text-brand-acid" aria-hidden="true" />
        </div>
        <div class="flex flex-1 flex-wrap items-center gap-x-4 gap-y-2 py-3 sm:gap-x-6">
            <template v-if="budget.hasTarget">
                <progress
                    class="sr-only"
                    :value="budget.progress"
                    max="100"
                    aria-label="Daily calorie target"
                    :aria-valuetext="`${budget.percentLabel} of target eaten`"
                />
                <div class="relative grid size-20 shrink-0 place-items-center sm:size-24" aria-hidden="true">
                    <svg viewBox="0 0 96 96" class="absolute inset-0 size-full -rotate-90" fill="none" stroke-width="8">
                        <circle cx="48" cy="48" r="42" class="stroke-brand-white/20 forced-colors:[stroke:GrayText]" />
                        <circle
                            cx="48" cy="48" r="42" pathLength="100"
                            stroke-dasharray="100"
                            :stroke-dashoffset="100 - budget.progress"
                            class="transition-[stroke-dashoffset,stroke] duration-200 ease-out motion-reduce:transition-none forced-colors:[stroke:CanvasText]"
                            :class="budget.overTarget ? 'stroke-fat' : 'stroke-brand-acid'"
                        />
                    </svg>
                    <span class="text-base font-semibold tabular-nums">{{ budget.percentLabel }}</span>
                </div>
            </template>
            <div class="min-w-0">
                <p class="font-heading text-[clamp(2.5rem,10vw,4rem)] leading-none font-black tracking-[-0.065em] italic tabular-nums">
                    {{ calories.format(budget.amount) }}
                </p>
                <p class="mt-1 text-sm text-brand-white/80">{{ budget.caption }}</p>
            </div>
        </div>
        <div v-if="budget.hasTarget || burned" class="flex flex-wrap gap-x-4 gap-y-1 border-t border-brand-white/15 pt-3 text-sm text-brand-white/80">
            <span v-if="budget.hasTarget"><strong class="text-brand-white">{{ calories.format(consumed) }}</strong> eaten</span>
            <span v-if="budget.hasTarget"><strong class="text-brand-white">{{ calories.format(goal) }}</strong> target</span>
            <span v-if="burned"><strong class="text-brand-white">{{ calories.format(burned) }}</strong> burned</span>
        </div>
    </section>
</template>
