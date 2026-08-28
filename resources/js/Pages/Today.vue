<script setup lang="ts">
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import type {DateValue} from '@internationalized/date';
import {parseDate} from '@internationalized/date';
import {Apple, Calendar as CalendarIcon, Coffee, Drumstick, Dumbbell, EllipsisVertical, Plus, Pencil, RefreshCw, Sandwich, TrendingUp, Trash2, X} from '@lucide/vue';
import {formatDisplayDate} from '../dateFormat';
import {dayStatusLabel, type DayStatus} from '../dayStatus';
import { hapticImpact } from '../haptics';
import CalorieRing from '../Components/CalorieRing.vue';
import DayStatusIndicator from '../Components/DayStatusIndicator.vue';
import Card from '../Components/Card.vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import AppSheet from '../Components/AppSheet.vue';
import FoodThumbnail from '../Components/FoodThumbnail.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import {Calendar} from '../Components/ui/calendar';
import Input from '../Components/ui/input/Input.vue';
import {Popover, PopoverContent, PopoverTrigger} from '../Components/ui/popover';
import Progress from '../Components/ui/progress/Progress.vue';

type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';
type MacroKey = 'protein_g' | 'carbs_g' | 'fat_g';
type MealEditMode = 'portion' | 'macros';

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

interface AppleHealthState {
    is_ios: boolean;
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
    appleHealth: AppleHealthState;
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
const hasWorkouts = computed(() => Boolean(props.summary.workouts?.length));
const isEmptyDay = computed(() => !hasMeals.value && !hasWorkouts.value);
const isToday = computed(() => props.week.some((day) => day.is_selected && day.is_today));
const displayDate = computed(() => formatDisplayDate(props.summary.date, {weekday: 'short', year: false}));
const selectedDate = computed(() => parseDate(props.summary.date));
const shortWeekdayFormatter = new Intl.DateTimeFormat('en-GB', {weekday: 'short'});
const longWeekdayFormatter = new Intl.DateTimeFormat('en-GB', {weekday: 'long'});
const healthConnectState = ref({...props.healthConnect});
const appleHealthState = ref({...props.appleHealth});
const healthConnectLoading = ref(false);
const healthConnectRefreshTimer = ref<number | null>(null);
const healthConnectSummaryRefreshMarker = ref(healthConnectSummaryMarker(props.healthConnect.is_android ? props.healthConnect : props.appleHealth));
const healthImport = computed(() => {
    if (appleHealthState.value.is_ios === true) {
        return {name: 'Apple Health', prefix: '/apple-health', state: appleHealthState.value};
    }

    if (healthConnectState.value.is_android === true) {
        return {name: 'Health Connect', prefix: '/health-connect', state: healthConnectState.value};
    }

    return null;
});
const showHealthConnect = computed(() => healthImport.value !== null);
const canSyncHealthConnect = computed(() => ['connected', 'sync_queued'].includes(healthImport.value?.state.status ?? ''));
const selectedMeal = ref<SelectedMeal | null>(null);
const mealSheetMode = ref<'details' | 'edit' | null>(null);
const selectedWorkout = ref<WorkoutEntry | null>(null);
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

const editMealForm = useForm<{
    date: string;
    meal_type: MealType | '';
    name: string;
    edit_mode: MealEditMode;
    portion_quantity: number | null;
    portion_unit: string | null;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}>({
    date: props.summary.date,
    meal_type: '',
    name: '',
    edit_mode: 'macros',
    portion_quantity: null,
    portion_unit: null,
    protein_g: 0,
    carbs_g: 0,
    fat_g: 0,
});

const editWorkoutForm = useForm({
    date: props.summary.date,
    title: '',
    calories_burned: 0,
    time: '',
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
    const pending = pendingDelete.value;

    if (!pending) {
        return;
    }

    const {kind, id} = pending;
    pendingDelete.value = null;
    hapticImpact();

    if (kind === 'meal') {
        router.delete(`/meals/${id}`, {
            preserveScroll: true,
            onSuccess: closeMeal,
        });

        return;
    }

    router.delete(`/workouts/${id}`, {
        preserveScroll: true,
    });
}

function applyHealthImportState(data: {native?: Record<string, unknown>} & Record<string, unknown>) {
    const nextState = {...data, ...(data.native || {})};

    if (healthImport.value?.prefix === '/apple-health') {
        appleHealthState.value = {...appleHealthState.value, ...nextState};
        return;
    }

    healthConnectState.value = {...healthConnectState.value, ...nextState};
}

async function refreshHealthConnectStatus() {
    const prefix = healthImport.value?.prefix;

    if (!prefix) {
        return;
    }

    try {
        const {data} = await axios.get(`${prefix}/status`);
        applyHealthImportState(data);
        refreshTodaySummaryWhenHealthConnectChanged();
    } catch {
        applyHealthImportState({last_error: `Could not check ${healthImport.value?.name ?? 'health data'}.`});
    }
}

async function connectHealthConnect() {
    const prefix = healthImport.value?.prefix;

    if (!prefix) {
        return;
    }

    healthConnectLoading.value = true;

    try {
        const endpoint = healthImport.value?.state.status === 'connected' ? `${prefix}/sync` : `${prefix}/connect`;
        const {data} = await axios.post(endpoint);
        applyHealthImportState(data);
        refreshTodaySummaryWhenHealthConnectChanged();

        if (shouldPollHealthConnectStatus()) {
            scheduleHealthConnectStatusRefresh(20, healthImport.value?.state.status === 'sync_queued');
        }
    } finally {
        healthConnectLoading.value = false;
    }
}

async function syncHealthConnect() {
    const prefix = healthImport.value?.prefix;

    if (!prefix) {
        return;
    }

    healthConnectLoading.value = true;

    try {
        const {data} = await axios.post(`${prefix}/sync`);
        applyHealthImportState(data);
        refreshTodaySummaryWhenHealthConnectChanged();

        if (shouldPollHealthConnectStatus()) {
            scheduleHealthConnectStatusRefresh(20, healthImport.value?.state.status === 'sync_queued');
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
    return ['permission_requested', 'sync_queued'].includes(healthImport.value?.state.status ?? '');
}

function healthConnectSummaryMarker(state: {last_successful_sync_at?: string | null; last_status?: string | null; synced_records?: number | null; deleted_records?: number | null}) {
    return [
        state.last_successful_sync_at ?? '',
        state.last_status ?? '',
        state.synced_records ?? '',
        state.deleted_records ?? '',
    ].join('|');
}

function refreshTodaySummaryWhenHealthConnectChanged() {
    const marker = healthConnectSummaryMarker(healthImport.value?.state ?? {});

    if (marker === healthConnectSummaryRefreshMarker.value) {
        return;
    }

    healthConnectSummaryRefreshMarker.value = marker;
    router.reload({
        only: ['summary', 'week', 'healthConnect', 'appleHealth'],
    });
}

function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        refreshHealthConnectStatus();
    }
}

function selectDate(value: DateValue | DateValue[] | undefined, close: () => void) {
    const date = Array.isArray(value) ? value[0] : value;

    if (!date) {
        return;
    }

    close();

    if (date.toString() === props.summary.date) {
        return;
    }

    router.visit(`/?date=${date.toString()}`, {preserveScroll: true});
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
        edit_mode: selectedMeal.value.portion_quantity === null ? 'macros' : 'portion',
        portion_quantity: selectedMeal.value.portion_quantity,
        portion_unit: selectedMeal.value.portion_unit,
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

function startEditingWorkout(workout: WorkoutEntry, closeMenu: () => void) {
    closeMenu();
    hapticImpact();
    selectedWorkout.value = workout;
    editWorkoutForm.defaults({
        date: props.summary.date,
        title: workout.title,
        calories_burned: workout.calories_burned,
        time: workout.logged_time ?? '',
    });
    editWorkoutForm.reset();
    editWorkoutForm.clearErrors();
}

function closeWorkoutEditor() {
    selectedWorkout.value = null;
    editWorkoutForm.reset();
    editWorkoutForm.clearErrors();
}

function saveWorkoutEdit() {
    if (!selectedWorkout.value) {
        return;
    }

    hapticImpact();
    editWorkoutForm.put(`/workouts/${selectedWorkout.value.id}`, {
        preserveScroll: true,
        onSuccess: closeWorkoutEditor,
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

    if (healthImport.value?.prefix === '/health-connect') {
        healthConnectSummaryRefreshMarker.value = healthConnectSummaryMarker(healthConnectState.value);
    }
}, {deep: true});

watch(() => props.appleHealth, (appleHealth) => {
    appleHealthState.value = {...appleHealthState.value, ...appleHealth};

    if (healthImport.value?.prefix === '/apple-health') {
        healthConnectSummaryRefreshMarker.value = healthConnectSummaryMarker(appleHealthState.value);
    }
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
                <Popover v-slot="{ close }">
                    <PopoverTrigger as-child>
                        <Button variant="outline" size="icon" class="rounded-full" aria-label="Select date">
                            <CalendarIcon :size="20"/>
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0" align="end">
                        <Calendar
                            :model-value="selectedDate"
                            locale="en-GB"
                            layout="month-and-year"
                            initial-focus
                            @update:model-value="(value) => selectDate(value, close)"
                        />
                    </PopoverContent>
                </Popover>
            </template>
        </PageHeader>

        <nav class="grid grid-cols-7 gap-1 rounded-2xl bg-card p-1.5 shadow-card" aria-label="Week">
            <Link
                v-for="day in week"
                :key="day.date"
                :href="`/?date=${day.date}`"
                class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold"
                :class="day.is_selected ? 'bg-secondary text-foreground' : 'text-muted-foreground active:bg-muted'"
                :aria-label="`${weekdayLabel(day.date, 'long')} ${day.date}, ${dayStatusLabel(day.status)}${day.is_today ? ', today' : ''}`"
            >
                <span class="sm:hidden">{{ day.label }}</span>
                <span class="hidden sm:inline lg:hidden">{{ weekdayLabel(day.date, 'short') }}</span>
                <span class="hidden lg:inline">{{ weekdayLabel(day.date, 'long') }}</span>
                <DayStatusIndicator :status="day.status" :size="16" />
            </Link>
        </nav>

        <div v-if="!hasGoal" class="rounded-xl border border-warning/35 bg-warning-soft p-4 text-sm text-warning-soft-foreground">
            Set your daily calorie and macro goals before logging meals.
            <Link href="/goals" class="font-semibold underline">Set goals</Link>
        </div>

        <Card v-if="hasGoal && isEmptyDay && isToday">
            <div class="flex items-start gap-3">
                <div class="grid h-11 w-11 flex-none place-items-center rounded-xl bg-primary text-primary-foreground">
                    <Plus :size="20" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="card-title">Start today</h2>
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
                    <Progress class="mt-2 h-1.5" :model-value="macroProgress(macro.consumed, macro.goal)" :indicator-class="macro.color" />
                </Link>
            </div>
        </Card>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold tracking-tight">Meals</h2>
                <Button :as="Link" :href="`/add?mode=food&date=${summary.date}`" size="sm"><Plus class="w-4" />Add food</Button>
            </div>

            <Card class="py-0">
                <div class="-mx-5 divide-y divide-border/60">
                    <div v-for="mealType in mealTypes" :key="mealType" class="px-5 py-3">
                        <div class="flex items-center justify-between gap-2 text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <component :is="mealIcons[mealType]" class="w-4"></component>
                                <h3 class="font-semibold text-foreground">{{ mealLabels[mealType] }}</h3>
                            </div>
                            <Button
                                v-if="!summary.entries[mealType]?.length"
                                :as="Link"
                                :href="`/add?mode=food&date=${summary.date}&meal=${mealType}`"
                                variant="ghost"
                                size="sm"
                                class="pr-0"
                                :aria-label="`Add ${mealLabels[mealType].toLowerCase()}`"
                            >
                                <Plus class="w-4" />Add
                            </Button>
                        </div>

                        <div v-if="summary.entries[mealType]?.length" class="-mx-5 mt-1 divide-y divide-border/60">
                            <div v-for="entry in summary.entries[mealType]" :key="entry.id">
                                <Button
                                    variant="ghost"
                                    class="h-auto w-full min-w-0 items-center justify-between gap-3 rounded-none border-0 px-5 py-2.5 text-left active:translate-y-0"
                                    :aria-label="`View ${entry.name}`"
                                    @click="openMeal(entry, mealType, $event)"
                                >
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
                </div>
            </Card>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold tracking-tight">Workouts</h2>
                <div class="flex items-center gap-2">
                    <template v-if="showHealthConnect">
                        <Button
                            v-if="canSyncHealthConnect"
                            variant="outline"
                            size="sm"
                            class="rounded-full"
                            :disabled="healthConnectLoading"
                            aria-label="Sync health workouts"
                            @click="syncHealthConnect"
                        >
                            <RefreshCw :size="16" :class="{ 'animate-spin': healthConnectLoading }"/>
                            Sync
                        </Button>
                        <Button
                            v-else
                            size="sm"
                            :disabled="healthConnectLoading || !healthImport?.state.available"
                            @click="connectHealthConnect"
                        >
                            Connect
                        </Button>
                    </template>
                    <Button :as="Link" :href="`/add?mode=workout&date=${summary.date}`" size="sm"><Plus class="w-4" />Add workout</Button>
                </div>
            </div>

            <Card class="py-1">
                <div v-if="summary.workouts?.length" class="divide-y divide-border/60">
                    <div v-for="workout in summary.workouts" :key="workout.id" class="flex items-center gap-3 py-3">
                        <div class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-secondary text-link">
                            <Dumbbell :size="18"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ workout.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ workout.logged_time }}
                            </p>
                        </div>
                        <p class="min-w-10 shrink-0 text-center leading-none">
                            <span class="block text-sm font-semibold tabular-nums">{{ workout.calories_burned }}</span>
                            <span class="mt-1 block text-[10px] font-medium leading-none text-muted-foreground">kcal</span>
                        </p>
                        <Popover v-slot="{ close }">
                            <PopoverTrigger as-child>
                                <Button variant="ghost" size="icon" class="h-9 w-9 text-muted-foreground/70" aria-label="Workout actions">
                                    <EllipsisVertical :size="20"/>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent align="end" class="w-40 gap-0.5 p-1" role="group" aria-label="Workout actions">
                                <Button variant="ghost" size="sm" class="w-full justify-start" @click="startEditingWorkout(workout, close)">
                                    <Pencil :size="16"/>Edit
                                </Button>
                                <Button variant="ghost" size="sm" class="w-full justify-start text-destructive hover:text-destructive" @click="close(); requestDelete('workout', workout.id, 'Delete this workout?')">
                                    <Trash2 :size="16"/>Delete
                                </Button>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                <div v-else class="py-6 text-center text-sm text-muted-foreground">No workouts yet.</div>
                <dl v-if="summary.workouts?.length" class="-mx-5 border-t border-border/60 py-3 text-center">
                    <dt class="text-xs font-medium text-muted-foreground">Total burned</dt>
                    <dd class="mt-1 font-semibold tabular-nums">{{ summary.log.burned_calories }} kcal</dd>
                </dl>
            </Card>
        </section>

        <Button :as="Link" :href="`/weekly?date=${summary.date}`" variant="ghost" class="h-auto w-full justify-between rounded-2xl bg-card px-5 py-4 shadow-card">
            <span class="flex items-center gap-2">
                <TrendingUp :size="18" />
                Weekly roundup
            </span>
            <span class="text-sm font-medium text-muted-foreground">Calories & macros</span>
        </Button>

        <AppSheet :open="Boolean(mealSheetMode && selectedMeal)" labelled-by="meal-sheet-title" @close="closeMeal">
            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-200 ease-out"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="mealSheetMode === 'details'" key="details">
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
                    <FoodThumbnail v-if="selectedMeal.image_url" :src="selectedMeal.image_url" class="h-24 w-24" :icon-size="28" />
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
                </div>
                <div v-else key="edit">
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

                    <div v-if="selectedMeal.portion_quantity !== null" class="grid grid-cols-2 gap-2" role="group" aria-label="Nutrition edit mode">
                        <Button
                            v-for="mode in (['portion', 'macros'] as MealEditMode[])"
                            :key="mode"
                            type="button"
                            :variant="editMealForm.edit_mode === mode ? 'default' : 'surface'"
                            :aria-pressed="editMealForm.edit_mode === mode"
                            @click="editMealForm.edit_mode = mode"
                        >
                            {{ mode === 'portion' ? 'Portion' : 'Macros' }}
                        </Button>
                    </div>

                    <label v-if="editMealForm.edit_mode === 'portion'" class="block">
                        <span class="field-label">{{ editMealForm.portion_unit === null ? 'Servings' : 'Portion' }}</span>
                        <div class="mt-1 flex items-center gap-3">
                            <Input v-model.number="editMealForm.portion_quantity" type="number" min="0.1" step="0.1" class="text-right font-semibold" />
                            <span v-if="editMealForm.portion_unit" class="w-8 text-sm text-muted-foreground">{{ editMealForm.portion_unit }}</span>
                        </div>
                        <span v-if="editMealForm.errors.portion_quantity || editMealForm.errors.portion_unit" class="mt-1 block text-sm text-destructive" role="alert">
                            {{ editMealForm.errors.portion_quantity || editMealForm.errors.portion_unit }}
                        </span>
                    </label>

                    <div v-else class="grid grid-cols-3 gap-2">
                        <label v-for="field in editMealMacroFields" :key="field[0]">
                            <span class="field-label">{{ field[1] }}</span>
                            <Input v-model.number="editMealForm[field[0]]" type="number" min="0" step="0.1" class="mt-1 px-2 text-right font-semibold" />
                            <span v-if="editMealForm.errors[field[0]]" class="mt-1 block text-sm text-destructive" role="alert">{{ editMealForm.errors[field[0]] }}</span>
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
                </div>
            </Transition>
        </AppSheet>
        <AppSheet
            :open="selectedWorkout !== null"
            labelled-by="workout-sheet-title"
            :dismissible="!editWorkoutForm.processing"
            @close="closeWorkoutEditor"
        >
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 id="workout-sheet-title" class="text-xl font-semibold tracking-tight">Edit workout</h2>
                <Button variant="ghost" size="icon" class="rounded-full" aria-label="Close workout editor" :disabled="editWorkoutForm.processing" @click="closeWorkoutEditor">
                    <X :size="20"/>
                </Button>
            </div>

            <form class="space-y-4" @submit.prevent="saveWorkoutEdit">
                <label class="block">
                    <span class="field-label">Title</span>
                    <Input v-model="editWorkoutForm.title" type="text" class="mt-1" />
                    <span v-if="editWorkoutForm.errors.title" class="mt-1 block text-sm text-destructive" role="alert">{{ editWorkoutForm.errors.title }}</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <label>
                        <span class="field-label">Calories burnt</span>
                        <Input v-model.number="editWorkoutForm.calories_burned" type="number" min="1" step="1" class="mt-1 text-right font-semibold" />
                        <span v-if="editWorkoutForm.errors.calories_burned" class="mt-1 block text-sm text-destructive" role="alert">{{ editWorkoutForm.errors.calories_burned }}</span>
                    </label>
                    <label>
                        <span class="field-label">Time</span>
                        <Input v-model="editWorkoutForm.time" type="time" class="mt-1 font-semibold" />
                        <span v-if="editWorkoutForm.errors.time" class="mt-1 block text-sm text-destructive" role="alert">{{ editWorkoutForm.errors.time }}</span>
                    </label>
                </div>

                <Button class="w-full" :disabled="editWorkoutForm.processing">
                    Save workout
                </Button>
            </form>
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
