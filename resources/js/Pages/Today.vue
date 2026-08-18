<script setup lang="ts">
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {Apple, Calendar, Coffee, Drumstick, Dumbbell, Plus, Pencil, RefreshCw, Sandwich, TrendingUp, Trash2, X} from '@lucide/vue';
import {formatDisplayDate} from '../dateFormat';
import {dayStatusClass, dayStatusLabel, type DayStatus} from '../dayStatus';
import { hapticImpact } from '../haptics';
import CalorieRing from '../Components/CalorieRing.vue';
import Card from '../Components/Card.vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import AppSheet from '../Components/AppSheet.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';

type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';
type MacroKey = 'protein_g' | 'carbs_g' | 'fat_g';

interface DailyGoal {
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    macro_calories: number;
}

interface DailyTotals {
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    calories_remaining: number;
    protein_remaining: number;
    carbs_remaining: number;
    fat_remaining: number;
}

interface MealEntry {
    id: string;
    name: string;
    meal_type?: MealType;
    source_type: string;
    portion_quantity: number | null;
    portion_unit: string | null;
    brand?: string | null;
    image_url?: string | null;
    serving_label?: string | null;
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

type SelectedMeal = MealEntry & { meal_type: MealType };

interface WorkoutEntry {
    id: string;
    title: string;
    calories_burned: number;
    logged_time: string | null;
    source_type: string;
    external_source?: string | null;
}

interface DailySummary {
    date: string;
    goal: DailyGoal | null;
    log: {
        burned_calories: number;
    };
    totals: DailyTotals;
    entries: Partial<Record<MealType, MealEntry[]>>;
    workouts: WorkoutEntry[];
}

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

interface HealthConnectState {
    is_android: boolean;
    available: boolean;
    supported: boolean;
    status: string;
    last_successful_sync_at?: string | null;
    last_synced_at?: string | null;
    last_status?: string | null;
    synced_records?: number | null;
    deleted_records?: number | null;
    last_error?: string | null;
    message?: string | null;
    has_permissions?: boolean | null;
    foreground_granted?: boolean | null;
    background_available?: boolean | null;
    background_granted?: boolean | null;
}

interface MacroCard {
    key: MacroKey;
    slug: string;
    label: string;
    consumed: number;
    goal?: number;
    remaining: number;
    color: string;
}

interface MealPhoto {
    id: string;
    url: string;
    mime_type: string;
}

const props = defineProps<{
    summary: DailySummary;
    week: WeekDay[];
    mealTypes: MealType[];
    healthConnect: HealthConnectState;
}>();

const mealLabels: Record<MealType, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

const mealIcons = {
    breakfast: Coffee,
    lunch: Sandwich,
    dinner: Drumstick,
    snacks: Apple,
};

const hasGoal = computed(() => Boolean(props.summary.goal));
const hasMeals = computed(() => props.mealTypes.some((mealType) => Boolean(props.summary.entries[mealType]?.length)));
const populatedMealTypes = computed(() => props.mealTypes.filter((mealType) => Boolean(props.summary.entries[mealType]?.length)));
const hasWorkouts = computed(() => Boolean(props.summary.workouts?.length));
const isEmptyDay = computed(() => !hasMeals.value && !hasWorkouts.value);
const displayDate = computed(() => formatDisplayDate(props.summary.date, {weekday: 'short'}));
const shortWeekdayFormatter = new Intl.DateTimeFormat('en-GB', {weekday: 'short'});
const longWeekdayFormatter = new Intl.DateTimeFormat('en-GB', {weekday: 'long'});
const healthConnectState = ref({...props.healthConnect});
const healthConnectLoading = ref(false);
const healthConnectRefreshTimer = ref<number | null>(null);
const healthConnectSummaryRefreshMarker = ref(healthConnectSummaryMarker(healthConnectState.value));
const showHealthConnect = computed(() => healthConnectState.value.is_android === true);
const canSyncHealthConnect = computed(() => ['connected', 'sync_queued'].includes(healthConnectState.value.status));
const selectedMeal = ref<SelectedMeal | null>(null);
const mealSheetMode = ref<'details' | 'edit' | null>(null);
const selectedMealPhotos = ref<MealPhoto[]>([]);
const mealPhotosLoading = ref(false);
const pendingDelete = ref<null | { kind: 'meal' | 'workout'; id: string; title: string }>(null);
let mealRowTrigger: HTMLElement | null = null;
let mealPhotoRequest = 0;
const macros = computed<MacroCard[]>(() => [
    {key: 'protein_g', slug: 'protein', label: 'Protein', consumed: props.summary.totals.protein_g, goal: props.summary.goal?.protein_g, remaining: props.summary.totals.protein_remaining, color: 'bg-protein'},
    {key: 'carbs_g', slug: 'carbs', label: 'Carbs', consumed: props.summary.totals.carbs_g, goal: props.summary.goal?.carbs_g, remaining: props.summary.totals.carbs_remaining, color: 'bg-carbs'},
    {key: 'fat_g', slug: 'fat', label: 'Fat', consumed: props.summary.totals.fat_g, goal: props.summary.goal?.fat_g, remaining: props.summary.totals.fat_remaining, color: 'bg-fat'},
]);

const editMealForm = useForm({
    date: props.summary.date,
    meal_type: '',
    name: '',
    protein_g: 0,
    carbs_g: 0,
    fat_g: 0,
});

const selectedMealMacros = computed(() => {
    if (!selectedMeal.value) {
        return [];
    }

    return [
        ['Protein', selectedMeal.value.protein_g, props.summary.goal?.protein_g],
        ['Carbs', selectedMeal.value.carbs_g, props.summary.goal?.carbs_g],
        ['Fat', selectedMeal.value.fat_g, props.summary.goal?.fat_g],
    ] as const;
});

const editMealMacroFields: ReadonlyArray<readonly [MacroKey, string]> = [
    ['protein_g', 'Protein'],
    ['carbs_g', 'Carbs'],
    ['fat_g', 'Fat'],
];

function macroProgress(consumed: number, goal?: number) {
    if (!goal) return 0;
    return Math.min(100, Math.round((consumed / goal) * 100));
}

function requestDelete(kind: 'meal' | 'workout', id: string, title: string) {
    pendingDelete.value = {kind, id, title};
}

function cancelDelete() {
    pendingDelete.value = null;
}

function confirmDelete() {
    if (!pendingDelete.value) {
        return;
    }

    const {kind, id} = pendingDelete.value;
    hapticImpact();

    if (kind === 'meal') {
        router.delete(`/meals/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeMeal();
                cancelDelete();
            },
        });

        return;
    }

    router.delete(`/workouts/${id}`, {
        preserveScroll: true,
        onSuccess: cancelDelete,
    });
}

async function refreshHealthConnectStatus() {
    try {
        const {data} = await axios.get('/health-connect/status');
        healthConnectState.value = {...healthConnectState.value, ...data};
        refreshTodaySummaryWhenHealthConnectChanged();
    } catch {
        healthConnectState.value = {...healthConnectState.value, last_error: 'Could not check Health Connect.'};
    }
}

async function connectHealthConnect() {
    healthConnectLoading.value = true;

    try {
        const endpoint = healthConnectState.value.status === 'connected' ? '/health-connect/sync' : '/health-connect/connect';
        const {data} = await axios.post(endpoint);
        healthConnectState.value = {...healthConnectState.value, ...data, ...(data.native || {})};
        refreshTodaySummaryWhenHealthConnectChanged();

        if (shouldPollHealthConnectStatus()) {
            scheduleHealthConnectStatusRefresh(20, healthConnectState.value.status === 'sync_queued');
        }
    } finally {
        healthConnectLoading.value = false;
    }
}

async function syncHealthConnect() {
    healthConnectLoading.value = true;

    try {
        const {data} = await axios.post('/health-connect/sync');
        healthConnectState.value = {...healthConnectState.value, ...data, ...(data.native || {})};
        refreshTodaySummaryWhenHealthConnectChanged();

        if (shouldPollHealthConnectStatus()) {
            scheduleHealthConnectStatusRefresh(20, healthConnectState.value.status === 'sync_queued');
        }
    } finally {
        healthConnectLoading.value = false;
    }
}

function handleWindowFocus() {
    if (showHealthConnect.value) {
        refreshHealthConnectStatus();
    }
}

function clearHealthConnectStatusRefresh() {
    if (healthConnectRefreshTimer.value === null) {
        return;
    }

    window.clearTimeout(healthConnectRefreshTimer.value);
    healthConnectRefreshTimer.value = null;
}

function scheduleHealthConnectStatusRefresh(attemptsRemaining = 20, waitForSummaryRefresh = false, initialSummaryMarker = healthConnectSummaryRefreshMarker.value) {
    clearHealthConnectStatusRefresh();

    if (attemptsRemaining < 1) {
        return;
    }

    healthConnectRefreshTimer.value = window.setTimeout(async () => {
        await refreshHealthConnectStatus();

        const summaryRefreshed = healthConnectSummaryRefreshMarker.value !== initialSummaryMarker;

        if (shouldPollHealthConnectStatus() || (waitForSummaryRefresh && ! summaryRefreshed)) {
            scheduleHealthConnectStatusRefresh(attemptsRemaining - 1, waitForSummaryRefresh, initialSummaryMarker);
        }
    }, 1000);
}

function shouldPollHealthConnectStatus() {
    return ['permission_requested', 'sync_queued'].includes(healthConnectState.value.status);
}

function healthConnectSummaryMarker(state: HealthConnectState) {
    return [
        state.last_successful_sync_at ?? '',
        state.last_status ?? '',
        state.synced_records ?? '',
        state.deleted_records ?? '',
    ].join('|');
}

function refreshTodaySummaryWhenHealthConnectChanged() {
    const marker = healthConnectSummaryMarker(healthConnectState.value);

    if (marker === healthConnectSummaryRefreshMarker.value) {
        return;
    }

    healthConnectSummaryRefreshMarker.value = marker;
    router.reload({
        only: ['summary', 'week', 'healthConnect'],
    });
}

function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        refreshHealthConnectStatus();
    }
}

function selectDate(event: Event) {
    const target = event.target instanceof HTMLInputElement ? event.target : null;

    if (target) {
        router.visit(`/?date=${target.value}`, {preserveScroll: true});
    }
}

function openMeal(entry: MealEntry, mealType: MealType, event: Event) {
    hapticImpact();
    mealRowTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    selectedMeal.value = {...entry, meal_type: mealType};
    mealSheetMode.value = 'details';
    loadMealPhotos(entry.id);
}

async function loadMealPhotos(mealId: string) {
    const request = ++mealPhotoRequest;
    selectedMealPhotos.value = [];

    mealPhotosLoading.value = true;

    try {
        const {data} = await axios.get(`/meals/${mealId}/photos`);

        if (request === mealPhotoRequest) {
            selectedMealPhotos.value = data.photos || [];
        }
    } catch {
        if (request === mealPhotoRequest) {
            selectedMealPhotos.value = [];
        }
    } finally {
        if (request === mealPhotoRequest) {
            mealPhotosLoading.value = false;
        }
    }
}

function closeMeal() {
    mealPhotoRequest++;
    selectedMeal.value = null;
    selectedMealPhotos.value = [];
    mealPhotosLoading.value = false;
    editMealForm.reset();
    editMealForm.clearErrors();
    mealSheetMode.value = null;
    mealRowTrigger?.focus();
    mealRowTrigger = null;
}

function startEditingMeal() {
    if (!selectedMeal.value) {
        return;
    }

    hapticImpact();
    editMealForm.defaults({
        date: props.summary.date,
        meal_type: selectedMeal.value.meal_type,
        name: selectedMeal.value.name,
        protein_g: selectedMeal.value.protein_g,
        carbs_g: selectedMeal.value.carbs_g,
        fat_g: selectedMeal.value.fat_g,
    });
    editMealForm.reset();
    editMealForm.clearErrors();
    mealSheetMode.value = 'edit';
}

function saveMealEdit() {
    if (!selectedMeal.value) {
        return;
    }

    hapticImpact();
    editMealForm.put(`/meals/${selectedMeal.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeMeal();
        },
    });
}

function macroPercent(consumed: number, goal?: number) {
    if (!goal) return 0;

    return Math.round((Number(consumed) / Number(goal)) * 100);
}

function weekdayLabel(value: string, format: 'short' | 'long') {
    const [year, month, day] = String(value).split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    const date = new Date(year, month - 1, day);

    return (format === 'short' ? shortWeekdayFormatter : longWeekdayFormatter).format(date);
}

watch(() => props.healthConnect, (healthConnect) => {
    healthConnectState.value = {...healthConnectState.value, ...healthConnect};
    healthConnectSummaryRefreshMarker.value = healthConnectSummaryMarker(healthConnectState.value);
}, {deep: true});

onMounted(() => {
    refreshHealthConnectStatus();
    window.addEventListener('focus', handleWindowFocus);
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    clearHealthConnectStatusRefresh();
    window.removeEventListener('focus', handleWindowFocus);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <Head title="Today"/>

    <section class="space-y-5">
        <PageHeader>
            {{ displayDate }}
            <template #actions>
                <Button as="label" variant="outline" size="icon" class="relative cursor-pointer overflow-hidden rounded-full" aria-label="Select date">
                    <Calendar :size="20"/>
                    <input
                        :value="summary.date"
                        type="date"
                        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                        aria-label="Select date"
                        @change="selectDate"
                    />
                </Button>
            </template>
        </PageHeader>

        <nav class="grid grid-cols-7 gap-1 rounded-2xl bg-card/70 p-1.5 shadow-card" aria-label="Week">
            <Link
                v-for="day in week"
                :key="day.date"
                :href="`/?date=${day.date}`"
                class="relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold transition"
                :class="day.is_selected ? 'bg-secondary text-foreground' : 'text-muted-foreground active:bg-muted'"
                :aria-label="`${weekdayLabel(day.date, 'long')} ${day.date}, ${dayStatusLabel(day.status)}${day.is_today ? ', today' : ''}`"
            >
                <span
                    v-if="day.is_today"
                    class="absolute top-1.5 h-1.5 w-1.5 rounded-full bg-primary"
                    aria-hidden="true"
                />
                <span class="sm:hidden">{{ day.label }}</span>
                <span class="hidden sm:inline lg:hidden">{{ weekdayLabel(day.date, 'short') }}</span>
                <span class="hidden lg:inline">{{ weekdayLabel(day.date, 'long') }}</span>
                <span class="h-1.5 w-1.5 rounded-full" :class="dayStatusClass(day.status)" aria-hidden="true"/>
            </Link>
        </nav>

        <div v-if="!hasGoal" class="rounded-xl border border-warning/35 bg-warning-soft p-4 text-sm text-warning-soft-foreground">
            Set your daily calorie and macro goals before logging meals.
            <Link href="/goals" class="font-semibold underline">Set goals</Link>
        </div>

        <Card v-if="hasGoal && isEmptyDay">
            <div class="flex items-start gap-3">
                <div class="grid h-11 w-11 flex-none place-items-center rounded-xl bg-primary text-primary-foreground">
                    <Plus :size="20" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">Start today</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Log a meal or workout to begin tracking this day.</p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <Button :as="Link" :href="`/add?mode=food&date=${summary.date}`" variant="default">Add food</Button>
                <Button :as="Link" :href="`/add?mode=workout&date=${summary.date}`" variant="outline">Add workout</Button>
            </div>
        </Card>

        <Card>
            <CalorieRing
                :consumed="summary.totals.calories"
                :goal="summary.goal?.calories ?? 0"
                :remaining="summary.totals.calories_remaining"
                :burned="summary.log.burned_calories"
            />
            <div class="mt-5 grid grid-cols-3 gap-4">
                <Link
                    v-for="macro in macros"
                    :key="macro.key"
                    :href="`/macros/${macro.slug}?date=${summary.date}`"
                    class="-mx-1 rounded-xl px-1 py-1 active:bg-muted"
                >
                    <p class="field-label">{{ macro.label }}</p>
                    <p class="mt-1.5 text-lg font-semibold tracking-tight">
                        {{ Math.round(macro.consumed ?? 0) }}g
                        <span class="text-xs font-medium text-muted-foreground">/ {{ Math.round(macro.goal ?? 0) }}g</span>
                    </p>
                    <div class="progress-track mt-2 h-1.5">
                        <div class="progress-fill" :class="macro.color" :style="{ width: `${macroProgress(macro.consumed, macro.goal)}%` }"/>
                    </div>
                </Link>
            </div>
        </Card>

        <section v-if="hasMeals" class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold tracking-tight">Meals</h2>
                <Button :as="Link" :href="`/add?mode=food&date=${summary.date}`" size="sm"><Plus class="w-4" />Add food</Button>
            </div>

            <Card class="py-2">
                <div v-for="mealType in populatedMealTypes" :key="mealType" class="py-3 first:pt-1 last:pb-1">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <component :is="mealIcons[mealType]" class="w-4"></component>
                    <h3 class="font-semibold text-foreground">{{ mealLabels[mealType] }}</h3>
                </div>

                <div class="mt-1 divide-y divide-border/60">
                    <div v-for="entry in summary.entries[mealType]" :key="entry.id" class="flex min-w-0 items-center gap-3 py-2.5">
                        <Button variant="ghost" class="h-auto min-w-0 flex-1 items-center justify-between gap-3 p-0 text-left" :aria-label="`View ${entry.name}`" @click="openMeal(entry, mealType, $event)">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-foreground">{{ entry.name }}</span>
                                <span class="block text-xs text-muted-foreground">
                                    {{ entry.portion_quantity }}{{ entry.portion_unit }}
                                </span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-sm font-semibold tabular-nums text-foreground">{{ entry.calories }}</span>
                                <span class="text-[10px] font-medium text-muted-foreground">kcal</span>
                            </span>
                        </Button>
                    </div>
                </div>
                </div>
            </Card>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold tracking-tight">Workouts</h2>
                <template v-if="showHealthConnect">
                    <Button
                        v-if="canSyncHealthConnect"
                        variant="outline"
                        size="icon"
                        class="rounded-full"
                        :disabled="healthConnectLoading"
                        aria-label="Sync Health Connect"
                        @click="syncHealthConnect"
                    >
                        <RefreshCw :size="16" :class="{ 'animate-spin': healthConnectLoading }"/>
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        :disabled="healthConnectLoading || !healthConnectState.available"
                        @click="connectHealthConnect"
                    >
                        Connect
                    </Button>
                </template>
            </div>

            <Card class="py-1">
                <div v-if="summary.workouts?.length" class="divide-y divide-border/60">
                    <div v-for="workout in summary.workouts" :key="workout.id" class="flex items-center gap-3 py-3">
                        <div class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-secondary text-primary">
                            <Dumbbell :size="18"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ workout.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ workout.logged_time }}
                            </p>
                        </div>
                        <p class="shrink-0 text-right">
                            <span class="block text-sm font-semibold tabular-nums">{{ workout.calories_burned }}</span>
                            <span class="text-[10px] font-medium text-muted-foreground">kcal</span>
                        </p>
                        <Button variant="ghost" size="icon" class="h-9 w-9 text-muted-foreground/70" aria-label="Remove workout" @click="requestDelete('workout', workout.id, 'Delete this workout?')">
                            <Trash2 :size="18"/>
                        </Button>
                    </div>
                </div>

                <div v-else class="py-6 text-center text-sm text-muted-foreground">No workouts yet.</div>
            </Card>
        </section>

        <Button :as="Link" :href="`/weekly?date=${summary.date}`" variant="outline" class="w-full justify-between rounded-2xl">
            <span class="flex items-center gap-2">
                <TrendingUp :size="18" />
                Weekly roundup
            </span>
            <span class="text-sm font-medium text-muted-foreground">Calories & macros</span>
        </Button>

        <AppSheet :open="Boolean(mealSheetMode && selectedMeal)" labelled-by="meal-sheet-title" @close="closeMeal">
                <template v-if="mealSheetMode === 'details'">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="field-label">{{ mealLabels[selectedMeal.meal_type] }}</p>
                        <h2 id="meal-sheet-title" class="mt-1 truncate text-xl font-semibold tracking-tight">{{ selectedMeal.name }}</h2>
                    </div>
                    <Button variant="ghost" size="icon" class="flex-none rounded-full" aria-label="Close meal details" @click="closeMeal">
                        <X :size="20"/>
                    </Button>
                </div>
                <div class="mt-4 flex min-w-0 gap-4">
                    <img v-if="selectedMeal.image_url" :src="selectedMeal.image_url" alt="" class="h-24 w-24 flex-none rounded-xl object-cover">
                    <div class="min-w-0 flex-1 text-sm text-muted-foreground">
                        <p v-if="selectedMeal.brand" class="truncate">{{ selectedMeal.brand }}</p>
                        <div>
                            {{ selectedMeal.calories }} kcal
                            <span v-if="selectedMeal.portion_quantity">
                             · {{ selectedMeal.portion_quantity }}{{ selectedMeal.portion_unit }}
                        </span>
                        </div>
                    </div>
                </div>
                <div v-if="mealPhotosLoading" class="mt-4 flex items-center gap-2 text-sm text-muted-foreground" role="status">
                    <RefreshCw :size="16" class="animate-spin" />
                    Loading meal photos…
                </div>
                <div v-else-if="selectedMealPhotos.length" class="mt-4 grid grid-cols-3 gap-2">
                    <img
                        v-for="photo in selectedMealPhotos"
                        :key="photo.id"
                        :src="photo.url"
                        alt="Meal photo"
                        class="aspect-square w-full rounded-xl object-cover"
                    >
                </div>
                <div class="mt-4 grid min-w-0 grid-cols-3 gap-2">
                    <div v-for="macro in selectedMealMacros" :key="macro[0]" class="min-w-0 rounded-xl bg-muted p-3 max-[360px]:p-2">
                        <p class="field-label truncate">{{ macro[0] }}</p>
                        <p class="mt-1 font-semibold">{{ macro[1] }}g</p>
                        <p class="truncate text-xs text-muted-foreground">{{ macroPercent(macro[1], macro[2]) }}% goal</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Button type="button" variant="surface" @click="startEditingMeal"><Pencil :size="18" />Edit</Button>
                    <Button type="button" variant="destructive" @click="requestDelete('meal', selectedMeal.id, 'Delete this meal?')"><Trash2 :size="18" />Delete</Button>
                </div>
                </template>
                <template v-else>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="meal-sheet-title" class="text-xl font-semibold tracking-tight">Edit meal</h2>
                    <Button variant="ghost" size="icon" class="rounded-full" aria-label="Close meal editor" @click="closeMeal">
                        <X :size="20"/>
                    </Button>
                </div>

                <form class="space-y-4" @submit.prevent="saveMealEdit">
                    <label class="block">
                        <span class="field-label">Name</span>
                        <Input v-model="editMealForm.name" type="text" class="mt-1" />
                    </label>

                    <div class="grid grid-cols-3 gap-2">
                        <label v-for="field in editMealMacroFields" :key="field[0]">
                            <span class="field-label">{{ field[1] }}</span>
                            <Input v-model.number="editMealForm[field[0]]" type="number" min="0" step="0.1" class="mt-1 px-2 text-right font-semibold" />
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 px-3 text-sm"
                            :variant="editMealForm.meal_type === mealType ? 'default' : 'surface'"
                            @click="editMealForm.meal_type = mealType"
                        >
                            {{ mealLabels[mealType] }}
                        </Button>
                    </div>

                    <Button class="w-full" :disabled="editMealForm.processing">
                        Save meal
                    </Button>
                </form>
                </template>
        </AppSheet>
        <ConfirmSheet
            :open="Boolean(pendingDelete)"
            :title="pendingDelete?.kind === 'workout' ? 'Delete workout' : 'Delete meal'"
            :message="pendingDelete?.title ?? ''"
            @cancel="cancelDelete"
            @confirm="confirmDelete"
        />
    </section>
</template>
