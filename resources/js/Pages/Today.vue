<script setup lang="ts">
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import {Apple, Calendar, Coffee, Drumstick, Dumbbell, EllipsisVertical, Plus, Info, Link2, Pencil, RefreshCw, Sandwich, TrendingUp, Trash2, X} from '@lucide/vue';
import {formatDisplayDate} from '../dateFormat';
import { hapticImpact } from '../haptics';
import Card from "../Components/Card.vue";
import Button from '../Components/ui/button/Button.vue';
import DropdownMenu from '../Components/ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuItem from '../Components/ui/dropdown-menu/DropdownMenuItem.vue';
import Input from '../Components/ui/input/Input.vue';

type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';
type MacroKey = 'protein_g' | 'carbs_g' | 'fat_g';
type DayStatus = 'target' | 'under' | 'over' | 'neutral';

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
    id: number;
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
    id: number;
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
    last_error?: string | null;
    message?: string | null;
    has_permissions?: boolean | null;
    foreground_granted?: boolean | null;
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
const hasWorkouts = computed(() => Boolean(props.summary.workouts?.length));
const isEmptyDay = computed(() => !hasMeals.value && !hasWorkouts.value);
const displayDate = computed(() => formatDisplayDate(props.summary.date, {weekday: 'short'}));
const shortWeekdayFormatter = new Intl.DateTimeFormat('en-GB', {weekday: 'short'});
const longWeekdayFormatter = new Intl.DateTimeFormat('en-GB', {weekday: 'long'});
const healthConnectState = ref({...props.healthConnect});
const healthConnectLoading = ref(false);
const showHealthConnect = computed(() => healthConnectState.value.is_android === true);
const canSyncHealthConnect = computed(() => ['connected', 'sync_queued'].includes(healthConnectState.value.status));
const selectedMeal = ref<SelectedMeal | null>(null);
const editingMeal = ref<SelectedMeal | null>(null);
const openMealActions = ref<number | null>(null);
const calorieProgress = computed(() => {
    if (!hasGoal.value || props.summary.goal.calories === 0) return 0;
    return Math.min(100, Math.round((props.summary.totals.calories / props.summary.goal.calories) * 100));
});

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

function removeEntry(id: number) {
    openMealActions.value = null;

    if (window.confirm('Delete this meal?')) {
        hapticImpact();
        router.delete(`/meals/${id}`, {preserveScroll: true});
    }
}

function removeWorkout(id: number) {
    hapticImpact();
    router.delete(`/workouts/${id}`, {preserveScroll: true});
}

const healthConnectLabel = computed(() => {
    if (!healthConnectState.value.available) return 'Unavailable';
    if (healthConnectState.value.status === 'connected') return 'Connected';
    if (healthConnectState.value.status === 'background_permission_required') return 'Background access needed';
    if (healthConnectState.value.status === 'sync_queued') return 'Sync queued';
    return 'Permission needed';
});

const healthConnectDetail = computed(() => {
    if (healthConnectState.value.message) {
        return healthConnectState.value.message;
    }

    if (healthConnectState.value.last_successful_sync_at) {
        if (Number(healthConnectState.value.synced_records || 0) === 0) {
            return `Last sync found no workouts · ${new Date(healthConnectState.value.last_successful_sync_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
        }

        return `Synced ${new Date(healthConnectState.value.last_successful_sync_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
    }

    if (healthConnectState.value.last_error) {
        return healthConnectState.value.last_error;
    }

    if (healthConnectState.value.last_synced_at) {
        return `Last checked ${new Date(healthConnectState.value.last_synced_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
    }

    return 'Automatically imports workouts.';
});

const healthConnectMeta = computed(() => {
    if (!healthConnectState.value.supported) {
        return 'Android app only';
    }

    if (healthConnectState.value.foreground_granted === false) {
        return 'Workout permissions need review';
    }

    if (healthConnectState.value.background_granted === false) {
        return 'Background sync permission is off';
    }

    if (healthConnectState.value.last_status === 'error') {
        return 'Last background sync failed';
    }

    if (healthConnectState.value.last_synced_at) {
        return `Checked ${new Date(healthConnectState.value.last_synced_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
    }

    return null;
});

async function refreshHealthConnectStatus() {
    try {
        const {data} = await axios.get('/health-connect/status');
        healthConnectState.value = {...healthConnectState.value, ...data};
    } catch {
        healthConnectState.value = {...healthConnectState.value, last_error: 'Could not check Health Connect.'};
    }
}

async function connectHealthConnect() {
    healthConnectLoading.value = true;

    try {
        const {data} = await axios.post('/health-connect/connect');
        healthConnectState.value = {...healthConnectState.value, ...data, ...(data.native || {})};
    } finally {
        healthConnectLoading.value = false;
    }
}

async function syncHealthConnect() {
    healthConnectLoading.value = true;

    try {
        const {data} = await axios.post('/health-connect/sync');
        healthConnectState.value = {...healthConnectState.value, ...data, ...(data.native || {})};
    } finally {
        healthConnectLoading.value = false;
    }
}

function handleWindowFocus() {
    if (showHealthConnect.value) {
        refreshHealthConnectStatus();
    }
}

function dayStatusClass(status: DayStatus) {
    return {
        target: 'bg-success/100',
        under: 'bg-warning',
        over: 'bg-fat',
        neutral: 'bg-muted-foreground/35',
    }[status] || 'bg-muted-foreground/35';
}

function selectDate(event: Event) {
    const target = event.target instanceof HTMLInputElement ? event.target : null;

    if (target) {
        router.visit(`/?date=${target.value}`, {preserveScroll: true});
    }
}

function openMeal(entry: MealEntry, mealType: MealType) {
    openMealActions.value = null;
    hapticImpact();
    selectedMeal.value = {...entry, meal_type: mealType};
}

function startEditingMeal(entry: MealEntry, mealType: MealType) {
    openMealActions.value = null;
    hapticImpact();
    editingMeal.value = {...entry, meal_type: mealType};
    editMealForm.defaults({
        date: props.summary.date,
        meal_type: mealType,
        name: entry.name,
        protein_g: entry.protein_g,
        carbs_g: entry.carbs_g,
        fat_g: entry.fat_g,
    });
    editMealForm.reset();
    editMealForm.clearErrors();
}

function saveMealEdit() {
    if (!editingMeal.value) {
        return;
    }

    hapticImpact();
    editMealForm.put(`/meals/${editingMeal.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingMeal.value = null;
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

function toggleMealActions(entryId: number) {
    openMealActions.value = openMealActions.value === entryId ? null : entryId;
    hapticImpact(18);
}

onMounted(() => {
    if (showHealthConnect.value) {
        refreshHealthConnectStatus();
        window.addEventListener('focus', handleWindowFocus);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('focus', handleWindowFocus);
});
</script>

<template>
    <Head title="Today"/>

    <section class="space-y-5">
        <header class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm  text-muted-foreground">Buff</p>
                <h1 class="text-3xl font-semibold tracking-normal text-foreground">{{ displayDate }}</h1>
            </div>
            <Button as="label" variant="outline" size="icon" class="relative cursor-pointer overflow-hidden" aria-label="Select date">
                <Calendar :size="21"/>
                <input
                    :value="summary.date"
                    type="date"
                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                    aria-label="Select date"
                    @change="selectDate"
                />
            </Button>
        </header>

        <nav class="grid grid-cols-7 gap-2" aria-label="Week">
            <Link
                v-for="day in week"
                :key="day.date"
                :href="`/?date=${day.date}`"
                class="relative flex min-h-16 flex-col items-center justify-center gap-1 rounded-md border text-sm font-semibold transition active:bg-muted"
                :class="day.is_selected ? 'border-primary bg-secondary text-foreground' : 'border-transparent text-muted-foreground'"
                :aria-label="`${weekdayLabel(day.date, 'long')} ${day.date} ${day.status}`"
            >
                <span
                    v-if="day.is_today"
                    class="absolute top-1 h-1.5 w-1.5 rounded-full bg-primary"
                    aria-label="Today"
                />
                <span class="sm:hidden">{{ day.label }}</span>
                <span class="hidden sm:inline lg:hidden">{{ weekdayLabel(day.date, 'short') }}</span>
                <span class="hidden lg:inline">{{ weekdayLabel(day.date, 'long') }}</span>
                <span class="h-2.5 w-2.5 rounded-full" :class="dayStatusClass(day.status)"/>
            </Link>
        </nav>

        <div v-if="!hasGoal" class="rounded-md border border-warning/35 bg-warning-soft p-4 text-sm text-warning-soft-foreground">
            Set your daily calorie and macro goals before logging meals.
            <Link href="/goals" class="font-semibold underline">Set goals</Link>
        </div>

        <Card v-if="hasGoal && isEmptyDay" class="bg-secondary border-0">
            <div class="flex items-start gap-3">
                <div class="grid h-10 w-10 flex-none place-items-center rounded-md bg-primary text-primary-foreground">
                    <Plus :size="20" />
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">Start today</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Log a meal or workout to begin tracking this day.</p>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <Button :as="Link" :href="`/add?mode=food&date=${summary.date}`" variant="default">Add food</Button>
                <Button :as="Link" :href="`/add?mode=workout&date=${summary.date}`" variant="surface">Add workout</Button>
            </div>
        </Card>

        <Card>
            <div class="flex items-start justify-between gap-4">
                <div class="w-full">
                    <p class="text-sm  text-muted-foreground">Calories</p>
                    <div class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-4xl font-bold">{{ summary.totals.calories }}</span>
                        <span class="text-xs text-muted-foreground">/ {{ props.summary.goal?.calories ?? 0 }}</span>
                        <span class="text-xs text-muted-foreground ml-auto" v-if="summary.log.burned_calories">{{ summary.log.burned_calories }} burned</span>
                    </div>
                </div>
            </div>

            <div class="mt-1 h-3 overflow-hidden rounded bg-muted">
                <div class="h-full rounded bg-success/100" :style="{ width: `${calorieProgress}%` }"/>
            </div>
            <p class="mt-2 text-xs  text-muted-foreground">{{ summary.totals.calories_remaining }} calories remaining</p>
            <div class="grid grid-cols-3 mt-7 gap-5">
                <Link
                    v-for="macro in macros"
                    :key="macro.key"
                    :href="`/macros/${macro.slug}?date=${summary.date}`"
                    class="block rounded-md active:bg-muted"
                >
                    <p class="text-xs font-semibold uppercase text-muted-foreground">{{ macro.label }}</p>
                    <p class="mt-2 text-xl font-semibold">
                        {{ Math.round(macro.consumed ?? 0) }}g
                        <span class="text-xs  text-muted-foreground">/ {{ Math.round(macro.goal ?? 0) }}g</span>
                    </p>
                    <div class="mt-1 h-2 overflow-hidden rounded bg-muted">
                        <div class="h-full rounded" :class="macro.color" :style="{ width: `${macroProgress(macro.consumed, macro.goal)}%` }"/>
                    </div>
<!--                    <p class="text-xs  text-muted-foreground">{{ macroPercent(macro.consumed, macro.goal) }}%</p>-->
                </Link>
            </div>
        </Card>

        <section class="space-y-1">
            <div>
                <h2 class="text-lg font-semibold">Meals</h2>
            </div>

            <Card v-for="mealType in mealTypes" :key="mealType">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <component :is="mealIcons[mealType]" class="w-4"></component>
                        <h3 class="font-semibold">{{ mealLabels[mealType] }}</h3>
                    </div>
                    <Button
                        :as="Link"
                        size="sm"
                        :href="`/add?mode=food&meal=${mealType}`">
                        <Plus class="w-4"/>
                        Add
                    </Button>
                </div>

                <div v-if="summary.entries[mealType]?.length" class="mt-2 divide-y divide-border/70">
                    <div v-for="entry in summary.entries[mealType]" :key="entry.id" class="flex min-w-0 items-center gap-3 py-2">
                        <Button variant="ghost" class="h-auto min-w-0 flex-1 flex-col items-start gap-0 p-0 text-left" @click="openMeal(entry, mealType)">
                            <p class="truncate">{{ entry.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.calories }} kcal · {{ entry.portion_quantity }}{{ entry.portion_unit }}
                            </p>
                        </Button>
                        <DropdownMenu
                            :model-value="openMealActions === entry.id"
                            class="flex-none"
                            data-meal-actions
                            @update:model-value="openMealActions = $event ? entry.id : null"
                        >
                            <template #trigger>
                                <Button variant="ghost" size="icon" class="h-9 w-9 text-muted-foreground/70" aria-label="Meal actions" @click="toggleMealActions(entry.id)">
                                    <EllipsisVertical :size="18"/>
                                </Button>
                            </template>

                            <DropdownMenuItem @click="openMeal(entry, mealType)">
                                    <Info :size="16"/>
                                    Info
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="startEditingMeal(entry, mealType)">
                                    <Pencil :size="16"/>
                                    Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem variant="destructive" @click="removeEntry(entry.id)">
                                    <Trash2 :size="16"/>
                                    Delete
                            </DropdownMenuItem>
                        </DropdownMenu>
                    </div>
                </div>

                <div v-else>
                    <p class="mt-2 text-sm text-center text-muted-foreground">No entries yet.</p>
                </div>
            </Card>
        </section>

        <section class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Workouts</h2>
                <template v-if="showHealthConnect">
                    <Button
                        v-if="canSyncHealthConnect"
                        variant="outline"
                        size="sm"
                        :disabled="healthConnectLoading"
                        aria-label="Sync Health Connect"
                        @click="syncHealthConnect"
                    >
                        <RefreshCw :size="16" :class="{ 'animate-spin': healthConnectLoading }"/>
                        Re-sync
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

            <Card>
                <div v-if="showHealthConnect" class="mb-3 flex items-center gap-3 rounded-md border border-border bg-muted p-3">
                    <div class="grid h-10 w-10 flex-none place-items-center rounded-md bg-primary text-primary-foreground">
                        <Link2 :size="18"/>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="">{{ healthConnectLabel }}</p>
                        <p class="truncate text-sm text-muted-foreground">{{ healthConnectDetail }}</p>
                        <p v-if="healthConnectMeta" class="truncate text-xs text-muted-foreground/80">{{ healthConnectMeta }}</p>
                    </div>
                </div>

                <div v-if="summary.workouts?.length" class="divide-y divide-border/70">
                    <div v-for="workout in summary.workouts" :key="workout.id" class="flex items-center gap-3 py-3">
                        <div class="grid h-10 w-10 flex-none place-items-center rounded-md bg-secondary text-primary">
                            <Dumbbell :size="19"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate ">{{ workout.title }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ workout.calories_burned }} kcal burned · {{ workout.logged_time }}
<!--                                <span v-if="workout.source_type === 'health_connect'"> · Health Connect</span>-->
                            </p>
                        </div>
                        <Button variant="ghost" size="icon" class="h-9 w-9 text-muted-foreground/70" aria-label="Remove workout" @click="removeWorkout(workout.id)">
                            <Trash2 :size="18"/>
                        </Button>
                    </div>
                </div>

                <p v-else class="text-sm text-muted-foreground">No workouts yet.</p>
            </Card>
        </section>

        <Button :as="Link" :href="`/weekly?date=${summary.date}`" variant="outline" class="w-full justify-between">
            <span class="flex items-center gap-2">
                <TrendingUp :size="19" />
                Weekly roundup
            </span>
            <span class="text-sm text-muted-foreground">Calories & macros</span>
        </Button>

        <div v-if="selectedMeal" class="fixed inset-0 z-50 grid place-items-end bg-foreground/30 px-4 pb-4 sm:place-items-center sm:py-4" @click.self="selectedMeal = null">
            <Card class="w-full max-w-md overflow-hidden sm:max-w-lg">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase text-muted-foreground">{{ mealLabels[selectedMeal.meal_type] }}</p>
                        <h2 class="truncate text-xl font-semibold">{{ selectedMeal.name }}</h2>
                    </div>
                    <Button variant="ghost" size="icon" class="flex-none" aria-label="Close meal details" @click="selectedMeal = null">
                        <X :size="20"/>
                    </Button>
                </div>
                <div class="mt-4 flex min-w-0 gap-4">
                    <img v-if="selectedMeal.image_url" :src="selectedMeal.image_url" alt="" class="h-24 w-24 flex-none rounded-md object-cover">
                    <div class="min-w-0 flex-1 text-sm  text-muted-foreground">
                        <p v-if="selectedMeal.brand" class="truncate">{{ selectedMeal.brand }}</p>
                        <div>
                            {{ selectedMeal.calories }} kcal
                            <span v-if="selectedMeal.portion_quantity">
                             · {{ selectedMeal.portion_quantity }}{{ selectedMeal.portion_unit }}
                        </span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid min-w-0 grid-cols-3 gap-2">
                    <div v-for="macro in selectedMealMacros" :key="macro[0]" class="min-w-0 rounded-md bg-muted p-3 max-[360px]:p-2">
                        <p class="truncate text-xs font-semibold uppercase text-muted-foreground">{{ macro[0] }}</p>
                        <p class="mt-1 font-semibold">{{ macro[1] }}g</p>
                        <p class="truncate text-xs  text-muted-foreground">{{ macroPercent(macro[1], macro[2]) }}% goal</p>
                    </div>
                </div>
            </Card>
        </div>

        <div v-if="editingMeal" class="fixed inset-0 z-50 grid place-items-end bg-foreground/30 px-4 pb-4 sm:place-items-center sm:py-4" @click.self="editingMeal = null">
            <Card class="w-full max-w-md sm:max-w-lg">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold">Edit meal</h2>
                    <Button variant="ghost" size="icon" aria-label="Close meal editor" @click="editingMeal = null">
                        <X :size="20"/>
                    </Button>
                </div>

                <form class="space-y-4" @submit.prevent="saveMealEdit">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Name</span>
                        <Input v-model="editMealForm.name" type="text" class="mt-1" />
                    </label>

                    <div class="grid grid-cols-3 gap-2">
                        <label v-for="field in editMealMacroFields" :key="field[0]">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">{{ field[1] }}</span>
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
            </Card>
        </div>
    </section>
</template>
