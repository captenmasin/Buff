<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { formatDisplayDate } from '../dateFormat';

type DayStatus = 'target' | 'under' | 'over' | 'neutral';

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

function dayStatusClass(status: DayStatus) {
    return {
        target: 'bg-success/100',
        under: 'bg-warning',
        over: 'bg-fat',
        neutral: 'bg-muted-foreground/35',
    }[status] || 'bg-muted-foreground/35';
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
        <header class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm text-muted-foreground">{{ formatDisplayDate(roundup.start_date) }} - {{ formatDisplayDate(roundup.end_date) }}</p>
                <h1 class="text-3xl font-semibold tracking-normal text-foreground">Weekly roundup</h1>
            </div>
            <Button :as="Link" :href="`/?date=${selectedDate}`" variant="outline" size="icon" aria-label="Back to today">
                <ArrowLeft :size="20" />
            </Button>
        </header>

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
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Week containing</span>
                    <Input v-model="weekDate" type="date" class="mt-1" />
                </label>

                <div v-else class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Start</span>
                        <Input v-model="startDate" type="date" class="mt-1" />
                    </label>
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">End</span>
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
                    <p class="mt-1 text-4xl font-bold">{{ roundup.calories }}<span class="text-sm text-muted-foreground"> kcal</span></p>
                </div>
                <div class="text-right text-sm text-muted-foreground">
                    <p v-if="roundup.effective_target">{{ roundup.effective_target }} kcal target</p>
                    <p v-if="roundup.burned_calories">{{ roundup.burned_calories }} burned</p>
                </div>
            </div>

            <div class="mt-3 h-3 overflow-hidden rounded bg-muted">
                <div class="h-full rounded bg-success" :style="{ width: `${progress(roundup.calories, roundup.effective_target)}%` }" />
            </div>

            <div class="mt-5 grid grid-cols-3 gap-3">
                <div v-for="macro in macroCards" :key="macro.label" class="min-w-0">
                    <p class="truncate text-xs font-semibold uppercase text-muted-foreground">{{ macro.label }}</p>
                    <p class="mt-1 text-lg font-semibold">
                        {{ Math.round(macro.consumed) }}g
                        <span class="text-xs text-muted-foreground">/ {{ Math.round(macro.goal ?? 0) }}g</span>
                    </p>
                    <div class="mt-1 h-2 overflow-hidden rounded bg-muted">
                        <div class="h-full rounded" :class="macro.color" :style="{ width: `${progress(macro.consumed, macro.goal)}%` }" />
                    </div>
                </div>
            </div>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Daily totals</h2>
            <Card class="divide-y divide-border/70">
                <div v-for="day in week" :key="day.date" class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                    <span class="grid h-10 w-10 flex-none place-items-center rounded-md bg-muted font-semibold">
                        {{ day.label }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ formatDisplayDate(day.date, { weekday: 'short' }) }}</p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ day.consumed_calories }} kcal
                            <span v-if="day.effective_target">/ {{ day.effective_target }}</span>
                            <span> · P {{ Math.round(day.protein_g) }}g · C {{ Math.round(day.carbs_g) }}g · F {{ Math.round(day.fat_g) }}g</span>
                        </p>
                    </div>
                    <span class="h-3 w-3 flex-none rounded-full" :class="dayStatusClass(day.status)" />
                </div>
            </Card>
        </section>
    </section>
</template>
