<script setup lang="ts">
import {Head, Link, router, useForm, usePage} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import type {DateValue} from '@internationalized/date';
import {getLocalTimeZone, parseDate, today} from '@internationalized/date';
import {Apple, Calendar as CalendarIcon, Check, ChevronRight, Coffee, Crown, Drumstick, Dumbbell, EllipsisVertical, Plus, Pencil, RefreshCw, Sandwich, TrendingUp, Trash2, X} from '@lucide/vue';
import {formatDisplayDate, parseLocalDate} from '../dateFormat';
import {dayStatusLabel, type DayStatus} from '../dayStatus';
import {responseErrorMessage} from '../foodRequests';
import { hapticImpact } from '../haptics';
import {mealLabels, mealSuggestionUrl, mealTypeForHour, type MealSuggestion, type MealType} from '../mealSuggestions';
import QuickMeal from '../Components/Add/QuickMeal.vue';
import CalorieRing from '../Components/CalorieRing.vue';
import DayStatusIndicator from '../Components/DayStatusIndicator.vue';
import Card from '../Components/Card.vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import AppSheet from '../Components/AppSheet.vue';
import FoodThumbnail from '../Components/FoodThumbnail.vue';
import Button from '../Components/ui/button/Button.vue';
import {Calendar} from '../Components/ui/calendar';
import Input from '../Components/ui/input/Input.vue';
import {Popover, PopoverContent, PopoverTrigger} from '../Components/ui/popover';
import Progress from '../Components/ui/progress/Progress.vue';
import {subscriptionPlatform, type SubscriptionAccount} from '../subscriptions';

const FloatingLines = defineAsyncComponent(() => import('../Components/FloatingLines.vue'));
const subscriptionLineGradient = ['#ffffff'];
const subscriptionLineWaves = ['middle' as const];
const subscriptionLinePosition = {x: 5, y: 0.82, rotate: 0.2};

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
    streak: number;
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

interface QuickMealLog {
    id: string;
    name: string;
    date: string;
}

const props = defineProps<{
    summary: DailySummary;
    week: WeekDay[];
    mealTypes: MealType[];
    healthConnect: HealthConnectState;
    appleHealth: AppleHealthState;
    subscriptionPromptEligible: boolean;
    mealSuggestions: Partial<Record<MealType, MealSuggestion>>;
    weeklyMealObservation: MealSuggestion | null;
    offerMealReminder: boolean;
}>();
const page = usePage<{
    errors?: {date?: string};
    buff?: {account?: SubscriptionAccount | null};
    flash?: {quick_meal_log?: QuickMealLog | null};
}>();

const mealIcons = {
    breakfast: Coffee,
    lunch: Sandwich,
    dinner: Drumstick,
    snacks: Apple,
};

const hasGoal = computed(() => Boolean(props.summary.goal));
const hasMeals = computed(() => props.mealTypes.some((mealType) => Boolean(props.summary.entries[mealType]?.length)));
const hasWorkouts = computed(() => Boolean(props.summary.workouts?.length));
const isToday = computed(() => props.week.some((day) => day.is_selected && day.is_today));
const currentMeal = ref(mealTypeForHour());
let lastLocalDate = today(getLocalTimeZone()).toString();
const suggestedMeal = computed(() => isToday.value ? props.mealSuggestions[currentMeal.value] : undefined);
const reminderDismissed = ref(false);
const quickMealLog = ref(page.props.flash?.quick_meal_log ?? null);
const undoMealForm = useForm({});
const undoableMeal = computed(() => {
    const logged = quickMealLog.value;
    if (!logged || logged.date !== props.summary.date) return null;

    return Object.values(props.summary.entries).some((entries) => entries.some((entry) => entry.id === logged.id))
        ? logged
        : null;
});

watch(() => page.props.flash?.quick_meal_log, (logged) => {
    if (logged) quickMealLog.value = logged;
});

function undoQuickMeal(): void {
    if (undoableMeal.value) {
        undoMealForm.delete(`/meals/${undoableMeal.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { quickMealLog.value = null; },
        });
    }
}

const displayDate = computed(() => formatDisplayDate(props.summary.date, {weekday: isToday.value ? 'short' : false, year: false}));
const addFoodUrl = computed(() => `/add?mode=food&date=${props.summary.date}${isToday.value ? `&meal=${currentMeal.value}` : ''}`);
const dateError = computed(() => page.props.errors?.date ?? '');
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
const mealPhotosError = ref('');
const mealPhotosCache = new Map<string, { photos: MealPhoto[]; expiresAt: number }>();
const subscriptionPromptOpen = ref(false);
const subscriptionPromptChecking = ref(false);
const showSubscriptionLines = ref(false);
const pendingDelete = ref<null | { kind: 'meal' | 'workout'; id: string; title: string }>(null);
const deleteProcessing = ref(false);
const deleteError = ref('');
let mealRowTrigger: HTMLElement | null = null;
let mealPhotoRequest = 0;

watch(subscriptionPromptOpen, (open) => {
    showSubscriptionLines.value = open && !document.documentElement.hasAttribute('data-reduce-motion');
});

async function checkSubscriptionPrompt(): Promise<void> {
    if (!props.subscriptionPromptEligible || !isToday.value || subscriptionPromptOpen.value || subscriptionPromptChecking.value || !navigator.onLine) {
        return;
    }

    const account = page.props.buff?.account;

    if (!account?.id || account.subscription?.entitled !== false) {
        return;
    }

    subscriptionPromptChecking.value = true;

    try {
        if (await subscriptionPlatform() === 'unsupported') {
            return;
        }

        const {data} = await axios.post('/subscription/prompt-seen');

        if (page.props.buff?.account?.id !== account.id) {
            return;
        }

        if (data?.claimed === true) {
            subscriptionPromptOpen.value = true;
        }
    } catch {
        // A failed claim leaves the prompt available for a later online visit.
    } finally {
        subscriptionPromptChecking.value = false;
    }
}

function viewSubscriptionPlans(): void {
    subscriptionPromptOpen.value = false;
    router.visit('/settings/subscription');
}
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

const repeatMealForm = useForm({
    date: props.summary.date,
    meal_type: 'breakfast' as MealType,
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

function mealPortionLabel(entry: MealEntry): string {
    if (entry.portion_quantity === null) {
        return '';
    }

    if (entry.source_type === 'recipe' || entry.portion_unit === null) {
        return `${entry.portion_quantity} serving${Number(entry.portion_quantity) === 1 ? '' : 's'}`;
    }

    return `${entry.portion_quantity}${entry.portion_unit}`;
}

function requestDelete(kind: 'meal' | 'workout', id: string, title: string) {
    deleteError.value = '';
    pendingDelete.value = {kind, id, title};
}

function cancelDelete() {
    if (deleteProcessing.value) {
        return;
    }

    deleteError.value = '';
    pendingDelete.value = null;
}

function confirmDelete() {
    const pending = pendingDelete.value;

    if (!pending || deleteProcessing.value) {
        return;
    }

    const {kind, id} = pending;
    deleteProcessing.value = true;
    deleteError.value = '';
    hapticImpact();

    router.delete(kind === 'meal' ? `/meals/${id}` : `/workouts/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;

            if (kind === 'meal') {
                closeMeal();
            }
        },
        onError: () => {
            deleteError.value = `Couldn’t delete this ${kind}. Try again.`;
        },
        onFinish: () => {
            deleteProcessing.value = false;

            if (pendingDelete.value && !deleteError.value) {
                deleteError.value = `Couldn’t delete this ${kind}. Try again.`;
            }
        },
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
    refreshMealContext();

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
        only: ['summary', 'week', 'healthConnect', 'appleHealth', 'mealSuggestions', 'weeklyMealObservation'],
    });
}

function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        refreshMealContext();
        refreshHealthConnectStatus();
    }
}

function refreshMealContext(): void {
    currentMeal.value = mealTypeForHour();
    const localDate = today(getLocalTimeZone()).toString();

    if (localDate !== lastLocalDate) {
        lastLocalDate = localDate;

        if (isToday.value) {
            router.visit('/', {replace: true});
        }
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

function closeSelectedDate(value: DateValue, close: () => void): void {
    if (value.toString() === props.summary.date) {
        close();
    }
}

function openMeal(entry: MealEntry, mealType: MealType, event: Event) {
    hapticImpact();
    mealRowTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    selectedMeal.value = {...entry, meal_type: mealType};
    repeatMealForm.date = props.summary.date;
    repeatMealForm.meal_type = mealType;
    repeatMealForm.clearErrors();
    mealSheetMode.value = 'details';
    loadMealPhotos(entry.id);
}

async function loadMealPhotos(mealId: string) {
    const request = ++mealPhotoRequest;
    selectedMealPhotos.value = [];
    mealPhotosError.value = '';
    const cached = mealPhotosCache.get(mealId);

    if (cached && cached.expiresAt > Date.now()) {
        selectedMealPhotos.value = cached.photos;
        mealPhotosLoading.value = false;
        return;
    }

    mealPhotosCache.delete(mealId);

    mealPhotosLoading.value = true;

    try {
        const {data} = await axios.get(`/meals/${mealId}/photos`);

        if (request === mealPhotoRequest) {
            selectedMealPhotos.value = data.photos || [];
            mealPhotosCache.set(mealId, {
                photos: selectedMealPhotos.value,
                expiresAt: Date.now() + (selectedMealPhotos.value.length ? 8 * 60_000 : 30_000),
            });
        }
    } catch (error) {
        if (request === mealPhotoRequest) {
            if (axios.isAxiosError(error) && error.response?.status === 404) {
                mealPhotosCache.set(mealId, {photos: [], expiresAt: Date.now() + 30_000});
            } else {
                mealPhotosError.value = responseErrorMessage(error, 'photos', 'Could not load meal photos. Check your connection and try again.');
            }
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
    mealPhotosError.value = '';
    editMealForm.reset();
    editMealForm.clearErrors();
    repeatMealForm.clearErrors();
    mealSheetMode.value = null;
    mealRowTrigger?.focus();
    mealRowTrigger = null;
}

function repeatMeal(): void {
    if (!selectedMeal.value) {
        return;
    }

    hapticImpact();
    repeatMealForm.post(`/meals/${selectedMeal.value.id}/repeat`, {
        preserveScroll: true,
        onSuccess: closeMeal,
    });
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
    const date = parseLocalDate(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

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

watch(() => [props.subscriptionPromptEligible, isToday.value, page.props.buff?.account?.id, page.props.buff?.account?.subscription?.entitled], () => {
    void checkSubscriptionPrompt();
});

onMounted(() => {
    refreshHealthConnectStatus();
    void checkSubscriptionPrompt();
    window.addEventListener('focus', handleWindowFocus);
    window.addEventListener('online', checkSubscriptionPrompt);
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    clearHealthConnectStatusRefresh();
    window.removeEventListener('focus', handleWindowFocus);
    window.removeEventListener('online', checkSubscriptionPrompt);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <Head title="Today"/>

    <section class="space-y-3 sm:space-y-4">
        <header class="flex items-center justify-between gap-2 sm:gap-3">
            <div class="min-w-0">
                <h1 class="page-title [--text-page-title:clamp(1.55rem,8.3vw,2rem)]">{{ isToday ? 'Today' : weekdayLabel(summary.date, 'long') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    <time :datetime="summary.date">{{ displayDate }}</time>
                    <Link v-if="!isToday" href="/" class="ml-2 inline-flex min-h-6 items-center font-semibold text-link underline underline-offset-2">Today</Link>
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
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
                            @day-click="(value) => closeSelectedDate(value, close)"
                            @update:model-value="(value) => selectDate(value, close)"
                        />
                    </PopoverContent>
                </Popover>
                <Button :as="Link" :href="addFoodUrl" class="min-h-11 rounded-full px-3.5">
                    <Plus :size="16" class="max-[360px]:hidden" aria-hidden="true" />Add food
                </Button>
            </div>
        </header>

        <p v-if="dateError" class="rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert">{{ dateError }}</p>

        <nav class="grid grid-cols-7 gap-0.5 rounded-2xl bg-card p-1.5 shadow-card sm:gap-1" aria-label="Week">
            <Link
                v-for="day in week"
                :key="day.date"
                :href="`/?date=${day.date}`"
                class="flex min-h-15 flex-col items-center justify-center gap-0.5 rounded-xl text-xs font-medium hover:bg-muted active:bg-muted"
                :class="day.is_selected ? 'bg-secondary text-foreground hover:bg-secondary' : 'text-muted-foreground'"
                :aria-current="day.is_selected ? 'date' : undefined"
                :aria-label="`${formatDisplayDate(day.date, {year: false})}, ${dayStatusLabel(day.status)}${day.is_today ? ', today' : ''}`"
            >
                <span>{{ weekdayLabel(day.date, 'short') }}</span>
                <span class="text-base leading-none font-semibold text-foreground tabular-nums">{{ Number(day.date.slice(-2)) }}</span>
                <DayStatusIndicator :status="day.status" :size="12" />
            </Link>
        </nav>

        <div v-if="!hasGoal" class="rounded-xl border border-warning/35 bg-warning-soft p-4 text-sm text-warning-soft-foreground">
            Set goals to see your remaining calories and macros.
            <Link href="/goals" class="font-semibold underline">Set goals</Link>
        </div>

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] sm:gap-4">
            <CalorieRing
                :consumed="summary.totals.calories"
                :goal="summary.goal?.calories ?? 0"
                :remaining="summary.totals.calories_remaining"
                :burned="summary.log.burned_calories"
            />
            <Card class="justify-between gap-1 px-4 py-3 sm:px-5 sm:py-4">
                <h2 class="text-lg font-semibold tracking-tight">Macros</h2>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-1 sm:gap-0">
                    <Link
                        v-for="macro in macros"
                        :key="macro.key"
                        :href="`/macros/${macro.slug}?date=${summary.date}`"
                        class="grid min-h-11 min-w-0 content-center gap-1.5 rounded-lg px-1.5 py-1.5 hover:bg-muted active:bg-muted sm:-mx-1.5"
                    >
                        <span class="flex flex-col gap-x-2 gap-y-0.5 sm:flex-row sm:items-baseline sm:justify-between">
                            <span class="flex items-center gap-1.5 text-xs font-semibold sm:text-sm">
                                <span class="size-1.5 shrink-0 rounded-full" :class="macro.color" aria-hidden="true" />{{ macro.label }}
                            </span>
                            <span class="text-xs tabular-nums sm:text-sm">
                                <strong>{{ Math.round(macro.consumed) }} g</strong>
                                <span v-if="macro.goal !== undefined" class="text-muted-foreground"> / {{ Math.round(macro.goal) }} g</span>
                            </span>
                        </span>
                        <Progress v-if="macro.goal" class="h-1.5" :model-value="macroProgress(macro.consumed, macro.goal)" :indicator-class="`${macro.color} motion-reduce:transition-none`" aria-hidden="true" />
                    </Link>
                </div>
            </Card>
        </div>

        <p class="sr-only" role="status">{{ undoableMeal ? `${undoableMeal.name} logged.` : '' }}</p>
        <Card v-if="undoableMeal" class="flex-row flex-wrap items-center justify-between gap-2">
            <p class="min-w-0 break-words">{{ undoableMeal.name }} logged.</p>
            <Button type="button" variant="outline" size="sm" :loading="undoMealForm.processing" loading-label="Undoing…" @click="undoQuickMeal">
                Undo
            </Button>
            <p v-if="undoMealForm.hasErrors" class="basis-full text-sm text-destructive" role="alert">{{ Object.values(undoMealForm.errors)[0] }}</p>
        </Card>
        <QuickMeal v-else-if="suggestedMeal" :key="`${summary.date}-${suggestedMeal.meal_type}-${suggestedMeal.id}`" :date="summary.date" :meal="suggestedMeal" />

        <Card v-if="offerMealReminder && !reminderDismissed" class="gap-3">
            <h2 class="card-title">Want a reminder to log a meal?</h2>
            <div class="flex flex-wrap gap-2">
                <Button :as="Link" href="/settings/reminders" size="sm">Set a reminder</Button>
                <Button type="button" variant="ghost" size="sm" @click="reminderDismissed = true">Not now</Button>
            </div>
        </Card>

        <div class="grid items-start gap-3 md:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)] sm:gap-4">
            <Card class="min-w-0 gap-2 px-4 py-3 sm:px-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold tracking-tight">Meals</h2>
                    <Button :as="Link" :href="addFoodUrl" size="sm"><Plus :size="16" aria-hidden="true" />Add food</Button>
                </div>

                <div v-if="hasMeals" class="divide-y divide-border border-t border-border">
                    <div v-for="mealType in mealTypes" :key="mealType" class="py-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-secondary text-foreground" aria-hidden="true">
                                    <component :is="mealIcons[mealType]" :size="16" />
                                </span>
                                <h3 class="font-semibold text-foreground">{{ mealLabels[mealType] }}</h3>
                            </div>
                            <Button
                                :as="Link"
                                :href="`/add?mode=food&date=${summary.date}&meal=${mealType}`"
                                variant="ghost"
                                size="icon"
                                class="size-11 rounded-lg"
                                :aria-label="`Add ${mealLabels[mealType].toLowerCase()}`"
                            >
                                <Plus :size="16" aria-hidden="true" />
                            </Button>
                        </div>

                        <div v-if="summary.entries[mealType]?.length" class="mt-1 divide-y divide-border/60">
                            <div v-for="entry in summary.entries[mealType]" :key="entry.id">
                                <Button
                                    variant="ghost"
                                    class="h-auto min-h-11 w-full min-w-0 items-center justify-between gap-2 rounded-lg border-0 px-2 py-2 text-left active:translate-y-0"
                                    @click="openMeal(entry, mealType, $event)"
                                >
                                    <span class="min-w-0">
                                        <span class="block whitespace-normal break-words font-medium text-foreground">{{ entry.name }}</span>
                                        <span v-if="entry.portion_quantity !== null" class="block text-xs text-muted-foreground">
                                            {{ mealPortionLabel(entry) }}
                                        </span>
                                    </span>
                                    <span class="ml-auto shrink-0 text-right text-xs text-muted-foreground">
                                        <strong class="text-sm font-semibold text-foreground tabular-nums">{{ entry.calories }}</strong> kcal
                                    </span>
                                    <ChevronRight :size="16" class="shrink-0 text-muted-foreground" aria-hidden="true" />
                                </Button>
                            </div>
                        </div>

                        <p v-else class="pb-2 pl-10 text-sm text-muted-foreground">Nothing logged</p>
                    </div>
                </div>
                <p v-else class="pb-1 text-sm text-muted-foreground">No meals logged.</p>
            </Card>

            <div class="grid min-w-0 gap-3 sm:gap-4">
                <Card class="gap-2 px-4 py-3 sm:px-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold tracking-tight">Workouts</h2>
                        <div class="flex flex-wrap justify-end gap-2">
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

                    <template v-if="hasWorkouts">
                        <div class="divide-y divide-border border-t border-border">
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
                                    <span class="mt-1 block text-xs font-medium leading-none text-muted-foreground">kcal</span>
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
                                        <Button variant="ghost" size="sm" class="w-full justify-start text-destructive hover:text-destructive" @click="close(); requestDelete('workout', workout.id, `Delete ${workout.title}?`)">
                                            <Trash2 :size="16"/>Delete
                                        </Button>
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>

                        <dl class="flex items-center justify-between gap-2 border-t border-border py-2">
                            <dt class="text-xs font-medium text-muted-foreground">Total burned</dt>
                            <dd class="font-semibold tabular-nums">{{ summary.log.burned_calories }} kcal</dd>
                        </dl>
                    </template>
                    <p v-else class="pb-1 text-sm text-muted-foreground">No workouts logged.</p>
                </Card>

                <Button :as="Link" :href="`/weekly?date=${summary.date}`" variant="ghost" class="h-auto min-h-12 w-full justify-start rounded-2xl bg-card px-4 py-3 shadow-card sm:px-5">
                    <TrendingUp :size="18" class="text-link" aria-hidden="true" />
                    Weekly roundup
                    <ChevronRight :size="16" class="ml-auto text-muted-foreground" aria-hidden="true" />
                </Button>
            </div>
        </div>

        <Card v-if="isToday && weeklyMealObservation" class="gap-3">
            <p class="break-words">
                <strong>{{ weeklyMealObservation.name }}</strong> was your highest-protein
                {{ weeklyMealObservation.meal_type === 'snacks' ? 'snack' : mealLabels[weeklyMealObservation.meal_type].toLowerCase() }}
                entry in the past 7 days ({{ weeklyMealObservation.protein_g }} g).
            </p>
            <Button :as="Link" :href="mealSuggestionUrl(summary.date, weeklyMealObservation)" variant="outline" size="sm" class="self-start">
                View entry
            </Button>
        </Card>

        <p class="text-center text-xs text-muted-foreground">🔥 {{ summary.streak }} day streak</p>

        <AppSheet
            :open="Boolean(mealSheetMode && selectedMeal)"
            labelled-by="meal-sheet-title"
            :title="mealSheetMode === 'edit' ? 'Edit meal' : 'Meal details'"
            description="Review, repeat, edit, or delete this meal."
            :dismissible="!editMealForm.processing && !repeatMealForm.processing"
            @close="closeMeal"
        >
            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-200 ease-out"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="mealSheetMode === 'details' && selectedMeal" key="details">
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
                             · {{ mealPortionLabel(selectedMeal) }}
                        </span>
                        </div>
                    </div>
                </div>
                <div v-if="mealPhotosLoading" class="mt-4 flex items-center gap-2 text-sm text-muted-foreground" role="status">
                    <RefreshCw :size="16" class="animate-spin" />
                    Loading meal photos…
                </div>
                <div v-else-if="mealPhotosError" class="mt-4 space-y-2 text-sm text-destructive" role="alert">
                    <p>{{ mealPhotosError }}</p>
                    <Button type="button" variant="surface" @click="loadMealPhotos(selectedMeal.id)">Retry meal photos</Button>
                </div>
                <div v-else-if="selectedMealPhotos.length" class="mt-4 grid grid-cols-3 gap-2">
                    <img
                        v-for="(photo, index) in selectedMealPhotos"
                        :key="photo.id"
                        :src="photo.url"
                        :loading="index === 0 ? 'eager' : 'lazy'"
                        :fetchpriority="index === 0 ? 'high' : 'low'"
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
                <div class="mt-4 grid gap-2">
                    <Button type="button" class="w-full" :loading="repeatMealForm.processing" loading-label="Repeating meal…" @click="repeatMeal"><RefreshCw :size="18" />Repeat</Button>
                    <p v-if="Object.values(repeatMealForm.errors)[0]" class="text-sm text-destructive" role="alert">{{ Object.values(repeatMealForm.errors)[0] }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <Button type="button" variant="surface" @click="startEditingMeal"><Pencil :size="18" />Edit</Button>
                        <Button type="button" variant="destructive" @click="requestDelete('meal', selectedMeal.id, `Delete ${selectedMeal.name} from ${mealLabels[selectedMeal.meal_type]}?`)"><Trash2 :size="18" />Delete</Button>
                    </div>
                </div>
                </div>
                <div v-else-if="mealSheetMode === 'edit' && selectedMeal" key="edit">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 id="meal-sheet-title" class="text-xl font-semibold tracking-tight">Edit meal</h2>
                    <Button variant="ghost" size="icon" class="rounded-full" aria-label="Close meal editor" :disabled="editMealForm.processing" @click="closeMeal">
                        <X :size="20"/>
                    </Button>
                </div>

                <form @submit.prevent="saveMealEdit">
                    <fieldset :disabled="editMealForm.processing" class="space-y-4">
                        <label class="block">
                            <span class="field-label">Name</span>
                            <Input v-model="editMealForm.name" type="text" class="mt-1" :aria-invalid="Boolean(editMealForm.errors.name)" :aria-describedby="editMealForm.errors.name ? 'meal-edit-name-error' : undefined" @update:model-value="editMealForm.clearErrors('name')" />
                            <span v-if="editMealForm.errors.name" id="meal-edit-name-error" class="mt-1 block text-sm text-destructive" role="alert">{{ editMealForm.errors.name }}</span>
                        </label>

                        <div v-if="selectedMeal.portion_quantity !== null" class="grid grid-cols-2 gap-1 rounded-xl bg-muted p-1 dark:bg-secondary" role="group" aria-label="Nutrition edit mode">
                            <Button
                                v-for="mode in (['portion', 'macros'] as MealEditMode[])"
                                :key="mode"
                                type="button"
                                variant="ghost"
                                class="h-11"
                                :class="editMealForm.edit_mode === mode ? 'bg-card text-foreground shadow-sm hover:bg-card' : 'text-muted-foreground'"
                                :aria-pressed="editMealForm.edit_mode === mode"
                                @click="editMealForm.edit_mode = mode"
                            >
                                {{ mode === 'portion' ? 'Portion' : 'Macros' }}
                            </Button>
                        </div>

                        <label v-if="editMealForm.edit_mode === 'portion'" class="block">
                            <span class="field-label">{{ editMealForm.portion_unit === null ? 'Servings' : 'Portion' }}</span>
                            <div class="mt-1 flex items-center gap-3">
                                <Input v-model.number="editMealForm.portion_quantity" type="number" min="0.1" step="0.01" class="text-right font-semibold" :aria-invalid="Boolean(editMealForm.errors.portion_quantity || editMealForm.errors.portion_unit)" :aria-describedby="editMealForm.errors.portion_quantity || editMealForm.errors.portion_unit ? 'meal-edit-portion-error' : undefined" @update:model-value="editMealForm.clearErrors('portion_quantity')" />
                                <span v-if="editMealForm.portion_unit" class="w-8 text-sm text-muted-foreground">{{ editMealForm.portion_unit }}</span>
                            </div>
                            <span v-if="editMealForm.errors.portion_quantity || editMealForm.errors.portion_unit" id="meal-edit-portion-error" class="mt-1 block text-sm text-destructive" role="alert">
                                {{ editMealForm.errors.portion_quantity || editMealForm.errors.portion_unit }}
                            </span>
                        </label>

                        <div v-else class="grid grid-cols-3 gap-2">
                            <label v-for="field in editMealMacroFields" :key="field[0]">
                                <span class="field-label">{{ field[1] }}</span>
                                <Input v-model.number="editMealForm[field[0]]" type="number" min="0" step="0.01" class="mt-1 px-2 text-right font-semibold" :aria-invalid="Boolean(editMealForm.errors[field[0]])" :aria-describedby="editMealForm.errors[field[0]] ? `meal-edit-${field[0]}-error` : undefined" @update:model-value="editMealForm.clearErrors(field[0])" />
                                <span v-if="editMealForm.errors[field[0]]" :id="`meal-edit-${field[0]}-error`" class="mt-1 block text-sm text-destructive" role="alert">{{ editMealForm.errors[field[0]] }}</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Meal" :aria-invalid="Boolean(editMealForm.errors.meal_type)" :aria-describedby="editMealForm.errors.meal_type ? 'meal-edit-meal-type-error' : undefined">
                            <Button
                                v-for="mealType in mealTypes"
                                :key="mealType"
                                type="button"
                                class="min-h-11 px-3 text-sm"
                                variant="outline"
                                :class="editMealForm.meal_type === mealType ? 'border-brand-violet bg-brand-violet/10 ring-1 ring-brand-violet hover:bg-brand-violet/10 dark:border-brand-violet dark:bg-brand-violet/10 dark:hover:bg-brand-violet/10' : ''"
                                :aria-pressed="editMealForm.meal_type === mealType"
                                @click="editMealForm.meal_type = mealType; editMealForm.clearErrors('meal_type')"
                            >
                                {{ mealLabels[mealType] }}
                            </Button>
                        </div>
                        <span v-if="editMealForm.errors.meal_type" id="meal-edit-meal-type-error" class="block text-sm text-destructive" role="alert">{{ editMealForm.errors.meal_type }}</span>

                        <Button class="w-full" :loading="editMealForm.processing" loading-label="Saving meal…">
                            Save meal
                        </Button>
                    </fieldset>
                </form>
                </div>
            </Transition>
        </AppSheet>
        <AppSheet
            :open="selectedWorkout !== null"
            labelled-by="workout-sheet-title"
            title="Edit workout"
            description="Update this workout’s details."
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
                    <Input v-model="editWorkoutForm.title" type="text" maxlength="120" class="mt-1" @update:model-value="editWorkoutForm.clearErrors('title')" />
                    <span v-if="editWorkoutForm.errors.title" class="mt-1 block text-sm text-destructive" role="alert">{{ editWorkoutForm.errors.title }}</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <label>
                        <span class="field-label">Calories burnt</span>
                        <Input v-model.number="editWorkoutForm.calories_burned" type="number" min="1" max="10000" step="1" class="mt-1 text-right font-semibold" @update:model-value="editWorkoutForm.clearErrors('calories_burned')" />
                        <span v-if="editWorkoutForm.errors.calories_burned" class="mt-1 block text-sm text-destructive" role="alert">{{ editWorkoutForm.errors.calories_burned }}</span>
                    </label>
                    <label>
                        <span class="field-label">Time</span>
                        <Input v-model="editWorkoutForm.time" type="time" class="mt-1 font-semibold" @update:model-value="editWorkoutForm.clearErrors('time')" />
                        <span v-if="editWorkoutForm.errors.time" class="mt-1 block text-sm text-destructive" role="alert">{{ editWorkoutForm.errors.time }}</span>
                    </label>
                </div>

                <Button class="w-full" :loading="editWorkoutForm.processing" loading-label="Saving workout…">
                    Save workout
                </Button>
            </form>
        </AppSheet>
        <ConfirmSheet
            :open="Boolean(pendingDelete)"
            :title="pendingDelete?.kind === 'workout' ? 'Delete workout' : 'Delete meal'"
            :message="pendingDelete?.title ?? ''"
            :processing="deleteProcessing"
            :processing-label="pendingDelete?.kind === 'workout' ? 'Deleting workout…' : 'Deleting meal…'"
            :error="deleteError"
            @cancel="cancelDelete"
            @confirm="confirmDelete"
        />
        <AppSheet
            :open="subscriptionPromptOpen"
            labelled-by="subscription-prompt-title"
            title="Meet Buff+"
            description="See what a Buff+ subscription adds."
            class="top-0 left-0 h-dvh max-h-dvh w-screen max-w-none translate-x-0 translate-y-0 rounded-none border-0 bg-brand-acid p-0 sm:max-w-none"
            @close="subscriptionPromptOpen = false"
        >
            <div class="relative -m-5 flex min-h-dvh flex-col overflow-x-hidden overflow-y-auto bg-brand-acid px-6 pb-[calc(env(safe-area-inset-bottom,0px)+1.5rem)] pt-[calc(env(safe-area-inset-top,0px)+1.5rem)] text-black">
                <div v-if="showSubscriptionLines" class="pointer-events-none absolute inset-0" aria-hidden="true">
                    <FloatingLines
                        class="opacity-65"
                        :lines-gradient="subscriptionLineGradient"
                        :enabled-waves="subscriptionLineWaves"
                        :line-count="3"
                        :line-distance="18"
                        :middle-wave-position="subscriptionLinePosition"
                        :animation-speed="1"
                        :interactive="false"
                        :parallax="false"
                    />
                </div>
                <div class="relative flex justify-end">
                    <Button type="button" variant="ghost" size="icon" class="rounded-full text-black hover:bg-black/10 hover:text-black dark:hover:bg-black/10" aria-label="Close Buff+ offer" @click="subscriptionPromptOpen = false">
                        <X :size="20" />
                    </Button>
                </div>
                <div class="relative mx-auto flex w-full max-w-sm flex-1 flex-col justify-center">
                    <div class="p-1 text-black">
                        <div class="mb-6 grid size-16 place-items-center rounded-2xl bg-black/10 text-black">
                            <Crown :size="32" aria-hidden="true" />
                        </div>
                        <h2 id="subscription-prompt-title" class="mt-3 text-4xl font-bold tracking-tight">Meet Buff+</h2>
                        <p class="mt-4 text-base leading-relaxed">You’ve built a meal logging habit. Get more from it with Buff+</p>
                        <ul class="mt-8 grid gap-4 text-base">
                            <li class="flex items-center gap-3 text-pretty"><Check :size="20" class="shrink-0" aria-hidden="true" /> AI meal analysis and follow-ups</li>
                            <li class="flex items-center gap-3"><Check :size="20" class="shrink-0" aria-hidden="true" /> An ad-free experience</li>
                            <li class="flex items-center gap-3"><Check :size="20" class="shrink-0" aria-hidden="true" /> Early access to future updates...</li>
                        </ul>
                        <div class="mt-8 grid gap-2">
                            <Button type="button" class="w-full bg-white text-black hover:bg-white/90" @click="viewSubscriptionPlans">See Buff+ plans</Button>
                            <Button type="button" variant="ghost" class="w-full text-black hover:bg-black/10 hover:text-black dark:hover:bg-black/10" @click="subscriptionPromptOpen = false">Maybe later</Button>
                        </div>
                    </div>
                </div>
            </div>
        </AppSheet>
    </section>
</template>
