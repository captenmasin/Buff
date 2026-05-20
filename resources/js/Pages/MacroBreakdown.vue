<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import Card from '../Components/Card.vue';
import { formatDisplayDate } from '../dateFormat';

const props = defineProps({
    date: { type: String, required: true },
    macro: { type: Object, required: true },
    entries: { type: Array, required: true },
});

const mealLabels = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

const macroColors = {
    protein_g: 'bg-sky-500',
    carbs_g: 'bg-orange-500',
    fat_g: 'bg-red-500',
};

const displayDate = computed(() => formatDisplayDate(props.date, { weekday: 'short' }));
const progressWidth = computed(() => Math.min(100, Math.max(0, Number(props.macro.current_percentage || 0))));

function grams(value) {
    return `${Math.round(Number(value || 0))}g`;
}
</script>

<template>
    <Head :title="`${macro.label} Breakdown`" />

    <section class="space-y-5">
        <header class="flex items-start gap-3">
            <Link :href="`/?date=${date}`" class="mt-1 grid h-10 w-10 flex-none place-items-center rounded-md border border-stone-200 bg-white text-stone-600 active:bg-stone-100" aria-label="Back to today">
                <ArrowLeft :size="20" />
            </Link>
            <div class="min-w-0">
                <p class="text-sm text-stone-500">{{ displayDate }}</p>
                <h1 class="text-3xl font-semibold tracking-normal text-[#17211b]">{{ macro.label }}</h1>
            </div>
        </header>

        <Card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-stone-500">Current split</p>
                    <p class="mt-2 text-4xl font-bold">{{ macro.current_percentage }}%</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-stone-500">Goal split</p>
                    <p class="mt-2 text-2xl font-semibold text-[#253d2c]">{{ macro.goal_percentage }}%</p>
                </div>
            </div>
            <div class="mt-4 h-3 overflow-hidden rounded bg-stone-100">
                <div class="h-full rounded" :class="macroColors[macro.key]" :style="{ width: `${progressWidth}%` }" />
            </div>
            <p class="mt-2 text-xs text-stone-500">
                {{ grams(macro.consumed_g) }} eaten<span v-if="macro.goal_g"> · {{ grams(macro.goal_g) }} daily goal</span>
            </p>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Foods</h2>

            <Card v-if="!entries.length">
                <p class="text-sm text-stone-500">No food logged for this day.</p>
            </Card>

            <Card v-for="entry in entries" :key="entry.id">
                <div class="flex gap-3">
                    <img v-if="entry.image_url" :src="entry.image_url" alt="" class="h-14 w-14 flex-none rounded-md object-cover">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ entry.name }}</p>
                                <p class="truncate text-xs text-stone-500">
                                    {{ mealLabels[entry.meal_type] ?? entry.meal_type }}<span v-if="entry.portion_quantity"> · {{ entry.portion_quantity }}{{ entry.portion_unit }}</span>
                                </p>
                            </div>
                            <p class="flex-none text-lg font-semibold">{{ grams(entry[macro.key]) }}</p>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div class="rounded-md bg-stone-50 p-2">
                                <p class="text-[11px] font-semibold uppercase text-stone-500">Protein</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.protein_g) }}</p>
                            </div>
                            <div class="rounded-md bg-stone-50 p-2">
                                <p class="text-[11px] font-semibold uppercase text-stone-500">Carbs</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.carbs_g) }}</p>
                            </div>
                            <div class="rounded-md bg-stone-50 p-2">
                                <p class="text-[11px] font-semibold uppercase text-stone-500">Fat</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.fat_g) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </section>
    </section>
</template>
