<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import Card from '../Components/Card.vue';
import PageHeader from '../Components/PageHeader.vue';
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
        <PageHeader :kicker="displayDate">
            {{ macro.label }}
            <template #actions>
                <Button :as="Link" :href="`/?date=${date}`" variant="outline" size="icon" class="rounded-full" aria-label="Back to today">
                    <ArrowLeft :size="20" />
                </Button>
            </template>
        </PageHeader>

        <Card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">Current split</p>
                    <p class="mt-2 text-4xl font-bold tracking-tight">{{ macro.current_percentage }}%</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-muted-foreground">Goal split</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-primary">{{ macro.goal_percentage }}%</p>
                </div>
            </div>
            <div class="progress-track mt-4 h-2.5">
                <div class="progress-fill" :class="macroColors[macro.key]" :style="{ width: `${progressWidth}%` }" />
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                {{ grams(macro.consumed_g) }} eaten<span v-if="macro.goal_g"> · {{ grams(macro.goal_g) }} daily goal</span>
            </p>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold tracking-tight">Foods</h2>

            <Card v-if="!entries.length">
                <p class="text-sm text-muted-foreground">No food logged for this day.</p>
            </Card>

            <Card v-for="entry in entries" :key="entry.id">
                <div class="flex gap-3">
                    <img v-if="entry.image_url" :src="entry.image_url" alt="" class="h-14 w-14 flex-none rounded-xl object-cover">
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
                            <div class="rounded-xl bg-muted p-2">
                                <p class="field-label">Protein</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.protein_g) }}</p>
                            </div>
                            <div class="rounded-xl bg-muted p-2">
                                <p class="field-label">Carbs</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.carbs_g) }}</p>
                            </div>
                            <div class="rounded-xl bg-muted p-2">
                                <p class="field-label">Fat</p>
                                <p class="mt-1 font-semibold">{{ grams(entry.fat_g) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </section>
    </section>
</template>
