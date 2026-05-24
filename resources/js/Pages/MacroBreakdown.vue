<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import { formatDisplayDate } from '../dateFormat';

type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';
type MacroKey = 'protein_g' | 'carbs_g' | 'fat_g';

interface MacroSummary {
    slug: string;
    key: MacroKey;
    label: string;
    consumed_g: number;
    goal_g: number;
    current_percentage: number;
    goal_percentage: number;
}

interface MacroEntry {
    id: number;
    meal_type: MealType;
    name: string;
    brand?: string | null;
    image_url?: string | null;
    portion_quantity: number | null;
    portion_unit: string | null;
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

const props = defineProps<{
    date: string;
    macro: MacroSummary;
    entries: MacroEntry[];
}>();

const mealLabels: Record<MealType, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

const macroColors: Record<MacroKey, string> = {
    protein_g: 'bg-protein',
    carbs_g: 'bg-carbs',
    fat_g: 'bg-fat',
};

const displayDate = computed(() => formatDisplayDate(props.date, { weekday: 'short' }));
const progressWidth = computed(() => Math.min(100, Math.max(0, Number(props.macro.current_percentage || 0))));

function grams(value: number | string | null | undefined) {
    return `${Math.round(Number(value || 0))}g`;
}
</script>

<template>
    <Head :title="`${macro.label} Breakdown`" />

    <section class="space-y-5">
        <header class="flex items-start gap-3">
            <Button :as="Link" :href="`/?date=${date}`" variant="outline" size="icon" class="mt-1 flex-none" aria-label="Back to today">
                <ArrowLeft :size="20" />
            </Button>
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">{{ displayDate }}</p>
                <h1 class="text-3xl font-semibold tracking-normal text-foreground">{{ macro.label }}</h1>
            </div>
        </header>

        <Card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">Current split</p>
                    <p class="mt-2 text-4xl font-bold">{{ macro.current_percentage }}%</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-muted-foreground">Goal split</p>
                    <p class="mt-2 text-2xl font-semibold text-primary">{{ macro.goal_percentage }}%</p>
                </div>
            </div>
            <div class="mt-4 h-3 overflow-hidden rounded bg-muted">
                <div class="h-full rounded" :class="macroColors[macro.key]" :style="{ width: `${progressWidth}%` }" />
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                {{ grams(macro.consumed_g) }} eaten<span v-if="macro.goal_g"> · {{ grams(macro.goal_g) }} daily goal</span>
            </p>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Foods</h2>

            <Card v-if="!entries.length">
                <p class="text-sm text-muted-foreground">No food logged for this day.</p>
            </Card>

            <Card v-for="entry in entries" :key="entry.id">
                <div class="flex gap-3">
                    <img v-if="entry.image_url" :src="entry.image_url" alt="" class="h-14 w-14 flex-none rounded-md object-cover">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ entry.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ mealLabels[entry.meal_type] ?? entry.meal_type }}<span v-if="entry.portion_quantity"> · {{ entry.portion_quantity }}{{ entry.portion_unit }}</span>
                                </p>
                            </div>
                            <p class="flex-none text-lg font-semibold">{{ grams(entry[macro.key]) }}</p>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <div class="rounded-md bg-muted p-2">
                                <p class="text-[11px] font-semibold uppercase text-muted-foreground">Protein</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.protein_g) }}</p>
                            </div>
                            <div class="rounded-md bg-muted p-2">
                                <p class="text-[11px] font-semibold uppercase text-muted-foreground">Carbs</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.carbs_g) }}</p>
                            </div>
                            <div class="rounded-md bg-muted p-2">
                                <p class="text-[11px] font-semibold uppercase text-muted-foreground">Fat</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.fat_g) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </section>
    </section>
</template>
