<script setup>
import {Head, Link, router, useForm} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import {Apple, Calendar, Coffee, Drumstick, Dumbbell, EllipsisVertical, Plus, Info, Link2, Pencil, RefreshCw, Sandwich, Trash2, X} from '@lucide/vue';
import {formatDisplayDate} from '../dateFormat';
import Card from "../Components/Card.vue";

const props = defineProps({
    summary: {type: Object, required: true},
    week: {type: Array, required: true},
    mealTypes: {type: Array, required: true},
    healthConnect: {type: Object, required: true},
});

const mealLabels = {
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
}

const hasGoal = computed(() => Boolean(props.summary.goal));
const displayDate = computed(() => formatDisplayDate(props.summary.date, {weekday: 'short'}));
const healthConnectState = ref({...props.healthConnect});
const healthConnectLoading = ref(false);
const datePickerOpen = ref(false);
const selectedMeal = ref(null);
const editingMeal = ref(null);
const openMealActions = ref(null);
const calorieProgress = computed(() => {
    if (!hasGoal.value || props.summary.goal.calories === 0) return 0;
    return Math.min(100, Math.round((props.summary.totals.calories / props.summary.goal.calories) * 100));
});

const macros = computed(() => [
    {key: 'protein_g', label: 'Protein', consumed: props.summary.totals.protein_g, goal: props.summary.goal?.protein_g, remaining: props.summary.totals.protein_remaining, color: 'bg-sky-500'},
    {key: 'carbs_g', label: 'Carbs', consumed: props.summary.totals.carbs_g, goal: props.summary.goal?.carbs_g, remaining: props.summary.totals.carbs_remaining, color: 'bg-orange-500'},
    {key: 'fat_g', label: 'Fat', consumed: props.summary.totals.fat_g, goal: props.summary.goal?.fat_g, remaining: props.summary.totals.fat_remaining, color: 'bg-red-500'},
]);

const editMealForm = useForm({
    date: props.summary.date,
    meal_type: '',
    name: '',
    protein_g: 0,
    carbs_g: 0,
    fat_g: 0,
});

function macroProgress(consumed, goal) {
    if (!goal) return 0;
    return Math.min(100, Math.round((consumed / goal) * 100));
}

function removeEntry(id) {
    openMealActions.value = null;

    if (window.confirm('Delete this meal?')) {
        router.delete(`/meals/${id}`, {preserveScroll: true});
    }
}

function removeWorkout(id) {
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
    if (healthConnectState.value.last_successful_sync_at) {
        if (Number(healthConnectState.value.synced_records || 0) === 0) {
            return 'Last sync found no workouts in Health Connect.';
        }

        return `Synced ${new Date(healthConnectState.value.last_successful_sync_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
    }

    if (healthConnectState.value.last_error) {
        return healthConnectState.value.last_error;
    }

    return 'Automatically imports workouts with calories.';
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
    if (healthConnectState.value.supported) {
        refreshHealthConnectStatus();
    }
}

function dayStatusClass(status) {
    return {
        target: 'bg-emerald-500',
        under: 'bg-amber-400',
        over: 'bg-red-500',
        neutral: 'bg-stone-300',
    }[status] || 'bg-stone-300';
}

function selectDate(event) {
    router.visit(`/?date=${event.target.value}`, {preserveScroll: true});
}

function openMeal(entry, mealType) {
    openMealActions.value = null;
    selectedMeal.value = {...entry, meal_type: mealType};
}

function startEditingMeal(entry, mealType) {
    openMealActions.value = null;
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
    editMealForm.put(`/meals/${editingMeal.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingMeal.value = null;
        },
    });
}

function macroPercent(consumed, goal) {
    if (!goal) return 0;

    return Math.round((Number(consumed) / Number(goal)) * 100);
}

onMounted(() => {
    if (healthConnectState.value.supported) {
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
                <p class="text-sm  text-stone-500">Buff</p>
                <h1 class="text-3xl font-semibold tracking-normal text-[#17211b]">{{ displayDate }}</h1>
            </div>
            <div class="relative">
                <button class="rounded-md border border-stone-200 bg-white p-2 text-stone-600 active:bg-stone-100" aria-label="Select date" @click="datePickerOpen = !datePickerOpen">
                    <Calendar :size="21"/>
                </button>
                <input
                    v-if="datePickerOpen"
                    :value="summary.date"
                    type="date"
                    class="absolute right-0 top-12 z-10 w-44 rounded-md border border-stone-200 bg-white px-3 py-2 text-sm  shadow"
                    @change="selectDate"
                >
            </div>
        </header>

        <nav class="grid grid-cols-7 gap-2" aria-label="Week">
            <Link
                v-for="day in week"
                :key="day.date"
                :href="`/?date=${day.date}`"
                class="relative flex min-h-16 flex-col items-center justify-center gap-1 rounded-md border text-sm font-semibold transition active:bg-stone-100"
                :class="day.is_selected ? 'border-[#253d2c] bg-[#dce8d4] text-[#17211b]' : 'border-transparent text-stone-600'"
                :aria-label="`${day.date} ${day.status}`"
            >
                <span
                    v-if="day.is_today"
                    class="absolute top-1 h-1.5 w-1.5 rounded-full bg-[#253d2c]"
                    aria-label="Today"
                />
                <span>{{ day.label }}</span>
                <span class="h-2.5 w-2.5 rounded-full" :class="dayStatusClass(day.status)"/>
            </Link>
        </nav>

        <div v-if="!hasGoal" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Set your daily calorie and macro goals before logging meals.
            <Link href="/goals" class="font-semibold underline">Set goals</Link>
        </div>

        <Card>
            <div class="flex items-start justify-between gap-4">
                <div class="w-full">
                    <p class="text-sm  text-stone-500">Calories</p>
                    <div class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-4xl font-bold">{{ summary.totals.calories }}</span>
                        <span class="text-xs text-stone-500">/ {{ props.summary.goal?.calories ?? 0 }}</span>
                        <span class="text-xs text-stone-500 ml-auto" v-if="summary.log.burned_calories">{{ summary.log.burned_calories }} burned</span>
                    </div>
                </div>
            </div>

            <div class="mt-1 h-3 overflow-hidden rounded bg-stone-100">
                <div class="h-full rounded bg-emerald-500" :style="{ width: `${calorieProgress}%` }"/>
            </div>
            <p class="mt-2 text-xs  text-stone-500">{{ summary.totals.calories_remaining }} calories remaining</p>
            <div class="grid grid-cols-3 mt-7 gap-5">
                <div
                    v-for="macro in macros"
                    :key="macro.key"
                >
                    <p class="text-xs font-semibold uppercase text-stone-500">{{ macro.label }}</p>
                    <p class="mt-2 text-xl font-semibold">
                        {{ Math.round(macro.consumed ?? 0) }}g
                        <span class="text-xs  text-stone-500">/ {{ Math.round(macro.goal ?? 0) }}g</span>
                    </p>
                    <div class="mt-1 h-2 overflow-hidden rounded bg-stone-100">
                        <div class="h-full rounded" :class="macro.color" :style="{ width: `${macroProgress(macro.consumed, macro.goal)}%` }"/>
                    </div>
                    <p class="text-xs  text-stone-500">{{ macroPercent(macro.consumed, macro.goal) }}%</p>
                </div>
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
                    <Link
                        class="flex h-7 gap-1 items-center rounded-md bg-[#253d2c] px-2 text-sm text-white disabled:opacity-60"
                        :href="`/add?mode=food&meal=${mealType}`">
                        <Plus class="w-4"/>
                        Add
                    </Link>
                </div>

                <div v-if="summary.entries[mealType]?.length" class="mt-2 divide-y divide-stone-100">
                    <div v-for="entry in summary.entries[mealType]" :key="entry.id" class="flex min-w-0 items-center gap-3 py-2">
                        <button class="min-w-0 flex-1 text-left" @click="openMeal(entry, mealType)">
                            <p class="truncate">{{ entry.name }}</p>
                            <p class="text-xs text-stone-500">
                                {{ entry.calories }} kcal · {{ entry.portion_quantity }}{{ entry.portion_unit }}
                            </p>
                        </button>
                        <div class="relative flex-none">
                            <button class="rounded p-2 text-stone-400 active:bg-stone-100" aria-label="Meal actions" @click="openMealActions = openMealActions === entry.id ? null : entry.id">
                                <EllipsisVertical :size="18"/>
                            </button>
                            <Card v-if="openMealActions === entry.id" class="absolute p-2 right-0 top-10 z-20 w-36 overflow-hidden">
                                <button class="flex w-full items-center gap-2 px-3 py-2 text-left active:bg-stone-100" @click="openMeal(entry, mealType)">
                                    <Info :size="16"/>
                                    Info
                                </button>
                                <button class="flex w-full items-center gap-2 px-3 py-2 text-left active:bg-stone-100" @click="startEditingMeal(entry, mealType)">
                                    <Pencil :size="16"/>
                                    Edit
                                </button>
                                <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-red-700 active:bg-red-50" @click="removeEntry(entry.id)">
                                    <Trash2 :size="16"/>
                                    Delete
                                </button>
                            </Card>
                        </div>
                    </div>
                </div>

                <div v-else>
                    <p class="mt-2 text-sm text-center text-stone-500">No entries yet.</p>
                </div>
            </Card>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Workouts</h2>
            </div>

            <Card>
                <div v-if="healthConnectState.supported" class="mb-3 flex items-center gap-3 rounded-md border border-stone-200 bg-stone-50 p-3">
                    <div class="grid h-10 w-10 flex-none place-items-center rounded-md bg-[#253d2c] text-white">
                        <Link2 :size="18"/>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="">{{ healthConnectLabel }}</p>
                        <p class="truncate text-sm text-stone-500">{{ healthConnectDetail }}</p>
                    </div>
                    <button
                        v-if="healthConnectState.status === 'connected' || healthConnectState.status === 'sync_queued'"
                        class="grid h-10 w-10 place-items-center rounded-md border border-stone-200 bg-white text-stone-700 disabled:opacity-60"
                        :disabled="healthConnectLoading"
                        aria-label="Sync Health Connect"
                        @click="syncHealthConnect"
                    >
                        <RefreshCw :size="17" :class="{ 'animate-spin': healthConnectLoading }"/>
                    </button>
                    <button
                        v-else
                        class="flex h-10 items-center rounded-md bg-[#253d2c] px-3 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="healthConnectLoading || !healthConnectState.available"
                        @click="connectHealthConnect"
                    >
                        Connect
                    </button>
                </div>

                <div v-if="summary.workouts?.length" class="divide-y divide-stone-100">
                    <div v-for="workout in summary.workouts" :key="workout.id" class="flex items-center gap-3 py-3">
                        <div class="grid h-10 w-10 flex-none place-items-center rounded-md bg-[#dce8d4] text-[#253d2c]">
                            <Dumbbell :size="19"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate ">{{ workout.title }}</p>
                            <p class="text-sm text-stone-500">
                                {{ workout.calories_burned }} kcal burned · {{ workout.logged_time }}<span v-if="workout.source_type === 'health_connect'"> · Health Connect</span>
                            </p>
                        </div>
                        <button class="rounded p-2 text-stone-400 active:bg-stone-100" aria-label="Remove workout" @click="removeWorkout(workout.id)">
                            <Trash2 :size="18"/>
                        </button>
                    </div>
                </div>

                <p v-else class="text-sm text-stone-500">No workouts yet.</p>
            </Card>
        </section>

        <div v-if="selectedMeal" class="fixed inset-0 z-50 grid place-items-end bg-black/30 px-4 pb-4" @click.self="selectedMeal = null">
            <Card class="w-full max-w-md overflow-hidden">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase text-stone-500">{{ mealLabels[selectedMeal.meal_type] }}</p>
                        <h2 class="truncate text-xl font-semibold">{{ selectedMeal.name }}</h2>
                    </div>
                    <button class="flex-none rounded-md p-2 text-stone-500 active:bg-stone-100" aria-label="Close meal details" @click="selectedMeal = null">
                        <X :size="20"/>
                    </button>
                </div>
                <div class="mt-4 flex min-w-0 gap-4">
                    <img v-if="selectedMeal.image_url" :src="selectedMeal.image_url" alt="" class="h-24 w-24 flex-none rounded-md object-cover">
                    <div class="min-w-0 flex-1 text-sm  text-stone-600">
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
                    <div v-for="macro in [
                        ['Protein', selectedMeal.protein_g, summary.goal?.protein_g],
                        ['Carbs', selectedMeal.carbs_g, summary.goal?.carbs_g],
                        ['Fat', selectedMeal.fat_g, summary.goal?.fat_g],
                    ]" :key="macro[0]" class="min-w-0 rounded-md bg-stone-50 p-3 max-[360px]:p-2">
                        <p class="truncate text-xs font-semibold uppercase text-stone-500">{{ macro[0] }}</p>
                        <p class="mt-1 font-semibold">{{ macro[1] }}g</p>
                        <p class="truncate text-xs  text-stone-500">{{ macroPercent(macro[1], macro[2]) }}% goal</p>
                    </div>
                </div>
            </Card>
        </div>

        <div v-if="editingMeal" class="fixed inset-0 z-50 grid place-items-end bg-black/30 px-4 pb-4" @click.self="editingMeal = null">
            <Card class="w-full max-w-md">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold">Edit meal</h2>
                    <button class="rounded-md p-2 text-stone-500 active:bg-stone-100" aria-label="Close meal editor" @click="editingMeal = null">
                        <X :size="20"/>
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="saveMealEdit">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-stone-500">Name</span>
                        <input v-model="editMealForm.name" type="text" class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]">
                    </label>

                    <div class="grid grid-cols-3 gap-2">
                        <label v-for="field in [
                            ['protein_g', 'Protein'],
                            ['carbs_g', 'Carbs'],
                            ['fat_g', 'Fat'],
                        ]" :key="field[0]">
                            <span class="text-xs font-semibold uppercase text-stone-500">{{ field[1] }}</span>
                            <input v-model.number="editMealForm[field[0]]" type="number" min="0" step="0.1" class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-2 py-3 text-right font-semibold outline-none focus:border-[#6f9b58]">
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 rounded px-3 text-sm font-semibold transition"
                            :class="editMealForm.meal_type === mealType ? 'bg-[#253d2c] text-white' : 'bg-stone-100 text-stone-600 active:bg-stone-200'"
                            @click="editMealForm.meal_type = mealType"
                        >
                            {{ mealLabels[mealType] }}
                        </button>
                    </div>

                    <button class="w-full rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="editMealForm.processing">
                        Save meal
                    </button>
                </form>
            </Card>
        </div>
    </section>
</template>
