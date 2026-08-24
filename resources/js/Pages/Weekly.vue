<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import Card from '../Components/Card.vue';
import DayStatusIndicator from '../Components/DayStatusIndicator.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Progress from '../Components/ui/progress/Progress.vue';
import { formatDisplayDate } from '../dateFormat';
import { dayStatusLabel, type DayStatus } from '../dayStatus';

interface WeekDay {
    date: string;
    label: string;
    status: DayStatus;
    is_today: boolean;
    is_selected: boolean;
    consumed_calories: number;
    burned_calories: number;
    effective_target?: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

interface WeekRoundup {
    start_date: string;
    end_date: string;
    calories: number;
    burned_calories: number;
    effective_target: number | null;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    protein_goal_g: number | null;
    carbs_goal_g: number | null;
    fat_goal_g: number | null;
}

interface WeekInsight {
    id: string;
    text: string;
}

const props = defineProps<{
    mode: 'week' | 'range';
    selectedDate: string;
    controls: {
        date: string;
        start_date: string;
        end_date: string;
    };
    week: WeekDay[];
    roundup: WeekRoundup;
    insights: WeekInsight[];
}>();

const selectedMode = ref<'week' | 'range'>(props.mode);
const weekDate = ref(props.controls.date);
const startDate = ref(props.controls.start_date);
const endDate = ref(props.controls.end_date);

const macroCards = [
    { label: 'Protein', consumed: props.roundup.protein_g, goal: props.roundup.protein_goal_g, color: 'bg-protein' },
    { label: 'Carbs', consumed: props.roundup.carbs_g, goal: props.roundup.carbs_goal_g, color: 'bg-carbs' },
    { label: 'Fat', consumed: props.roundup.fat_g, goal: props.roundup.fat_goal_g, color: 'bg-fat' },
];

function progress(consumed: number, goal?: number | null) {
    if (!goal) return 0;

    return Math.min(100, Math.round((consumed / goal) * 100));
}

function applySelection() {
    if (selectedMode.value === 'range') {
        router.visit(`/weekly?start_date=${startDate.value}&end_date=${endDate.value}`, {
            preserveScroll: true,
        });

        return;
    }

    router.visit(`/weekly?date=${weekDate.value}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Weekly roundup" />

    <section class="space-y-5">
        <PageHeader>
            Weekly roundup
            <template #actions>
                <Button :as="Link" :href="`/?date=${selectedDate}`" variant="outline" size="icon" class="rounded-full" aria-label="Back to today">
                    <ArrowLeft :size="20" />
                </Button>
            </template>
        </PageHeader>

        <Card>
            <div class="grid grid-cols-2 gap-2">
                <Button
                    type="button"
                    :variant="selectedMode === 'week' ? 'default' : 'surface'"
                    @click="selectedMode = 'week'"
                >
                    Week
                </Button>
                <Button
                    type="button"
                    :variant="selectedMode === 'range' ? 'default' : 'surface'"
                    @click="selectedMode = 'range'"
                >
                    Range
                </Button>
            </div>

            <form class="mt-4 space-y-3" @submit.prevent="applySelection">
                <label v-if="selectedMode === 'week'" class="block">
                    <span class="field-label">Week containing</span>
                    <Input v-model="weekDate" type="date" class="mt-1" />
                </label>

                <div v-else class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="field-label">Start</span>
                        <Input v-model="startDate" type="date" class="mt-1" />
                    </label>
                    <label>
                        <span class="field-label">End</span>
                        <Input v-model="endDate" type="date" class="mt-1" />
                    </label>
                </div>

                <Button class="w-full">
                    Apply
                </Button>
            </form>
        </Card>

        <Card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">Calories</p>
                    <p class="mt-1 text-4xl font-bold tracking-tight">{{ roundup.calories }}<span class="text-sm font-medium text-muted-foreground"> kcal</span></p>
                </div>
                <div class="text-right text-sm text-muted-foreground">
                    <p v-if="roundup.effective_target">{{ roundup.effective_target }} kcal target</p>
                    <p v-if="roundup.burned_calories">{{ roundup.burned_calories }} burned</p>
                </div>
            </div>

            <Progress class="mt-3 h-2.5" :model-value="progress(roundup.calories, roundup.effective_target)" indicator-class="bg-success" />

            <div class="mt-5 grid grid-cols-3 gap-3">
                <div v-for="macro in macroCards" :key="macro.label" class="min-w-0">
                    <p class="field-label truncate">{{ macro.label }}</p>
                    <p class="mt-1 text-lg font-semibold tracking-tight">
                        {{ Math.round(macro.consumed) }}g
                        <span class="text-xs font-medium text-muted-foreground">/ {{ Math.round(macro.goal ?? 0) }}g</span>
                    </p>
                    <Progress class="mt-1.5 h-1.5" :model-value="progress(macro.consumed, macro.goal)" :indicator-class="macro.color" />
                </div>
            </div>
        </Card>

        <Card v-if="insights.length">
            <h2 class="card-title">Insights</h2>
            <div class="mt-1 divide-y divide-border/60">
                <p v-for="insight in insights" :key="insight.id" class="py-3 text-sm first:pt-3 last:pb-1">
                    {{ insight.text }}
                </p>
            </div>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold tracking-tight">Daily totals</h2>
            <Card class="divide-y divide-border/60 px-0 py-1.5">
                <div v-for="day in week" :key="day.date" class="flex items-center gap-3 px-5 py-3.5">
                    <span class="grid size-6 flex-none place-items-center rounded-xl bg-muted font-semibold">
                        {{ day.label }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ formatDisplayDate(day.date, { weekday: 'short' }) }}</p>
                        <p class="mt-1 truncate text-sm text-muted-foreground">
                            {{ day.consumed_calories }} kcal
                            <span v-if="day.effective_target">/ {{ day.effective_target }}</span>
                            <span> · P {{ Math.round(day.protein_g) }}g · C {{ Math.round(day.carbs_g) }}g · F {{ Math.round(day.fat_g) }}g</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <DayStatusIndicator :status="day.status" :size="18" />
<!--                        <span class="max-w-24 truncate text-xs text-muted-foreground">{{ dayStatusLabel(day.status) }}</span>-->
                    </div>
                </div>
            </Card>
        </section>
    </section>
</template>
