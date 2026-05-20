<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Dumbbell, Link2, RefreshCw, Trash2 } from '@lucide/vue';
import { formatDisplayDate } from '../dateFormat';
import Card from "../Components/Card.vue";

const props = defineProps({
    summary: { type: Object, required: true },
    week: { type: Array, required: true },
    mealTypes: { type: Array, required: true },
    healthConnect: { type: Object, required: true },
});

const mealLabels = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

const hasGoal = computed(() => Boolean(props.summary.goal));
const displayDate = computed(() => formatDisplayDate(props.summary.date));
const healthConnectState = ref({ ...props.healthConnect });
const healthConnectLoading = ref(false);
const calorieProgress = computed(() => {
    if (!hasGoal.value || props.summary.goal.calories === 0) return 0;
    return Math.min(100, Math.round((props.summary.totals.calories / props.summary.goal.calories) * 100));
});

function macroProgress(consumed, goal) {
    if (!goal) return 0;
    return Math.min(100, Math.round((consumed / goal) * 100));
}

function removeEntry(id) {
    router.delete(`/meals/${id}`, { preserveScroll: true });
}

function removeWorkout(id) {
    router.delete(`/workouts/${id}`, { preserveScroll: true });
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

        return `Last synced ${new Date(healthConnectState.value.last_successful_sync_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}`;
    }

    if (healthConnectState.value.last_error) {
        return healthConnectState.value.last_error;
    }

    return 'Automatically imports workouts with calories.';
});

async function refreshHealthConnectStatus() {
    try {
        const { data } = await axios.get('/health-connect/status');
        healthConnectState.value = { ...healthConnectState.value, ...data };
    } catch {
        healthConnectState.value = { ...healthConnectState.value, last_error: 'Could not check Health Connect.' };
    }
}

async function connectHealthConnect() {
    healthConnectLoading.value = true;

    try {
        const { data } = await axios.post('/health-connect/connect');
        healthConnectState.value = { ...healthConnectState.value, ...data, ...(data.native || {}) };
    } finally {
        healthConnectLoading.value = false;
    }
}

async function syncHealthConnect() {
    healthConnectLoading.value = true;

    try {
        const { data } = await axios.post('/health-connect/sync');
        healthConnectState.value = { ...healthConnectState.value, ...data, ...(data.native || {}) };
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
    <Head title="Today" />

    <section class="space-y-5">
        <header>
            <div>
                <p class="text-sm font-semibold text-stone-500">Buff</p>
                <h1 class="text-3xl font-bold tracking-normal text-[#17211b]">{{ displayDate }}</h1>
            </div>
        </header>

        <nav class="grid grid-cols-7 gap-2" aria-label="Week">
            <Link
                v-for="day in week"
                :key="day.date"
                :href="`/?date=${day.date}`"
                class="relative flex min-h-16 flex-col items-center justify-center gap-1 rounded-md border text-sm font-bold transition active:bg-stone-100"
                :class="day.is_selected ? 'border-[#253d2c] bg-[#dce8d4] text-[#17211b]' : 'border-transparent text-stone-600'"
                :aria-label="`${day.date} ${day.status}`"
            >
                <span
                    v-if="day.is_today"
                    class="absolute top-1 h-1.5 w-1.5 rounded-full bg-[#253d2c]"
                    aria-label="Today"
                />
                <span>{{ day.label }}</span>
                <span class="h-2.5 w-2.5 rounded-full" :class="dayStatusClass(day.status)" />
            </Link>
        </nav>

        <div v-if="!hasGoal" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Set your daily calorie and macro goals before logging meals.
            <Link href="/goals" class="font-bold underline">Set goals</Link>
        </div>

        <Card>
            <div class="flex items-start justify-between gap-4">
                <div class="w-full">
                    <p class="text-sm font-semibold text-stone-500">Calories</p>
                    <div class="mt-2.5 flex items-baseline gap-2">
                        <span class="text-4xl font-semibold">{{ summary.totals.calories }}</span>
                        <span class="text-xs text-stone-500">/ {{ summary.totals.calories_remaining ?? 0 }}</span>
                        <span class="text-xs text-stone-500 ml-auto" v-if="summary.log.burned_calories">{{ summary.log.burned_calories }} burned</span>
                    </div>
                </div>
            </div>

            <div class="mt-1 h-3 overflow-hidden rounded bg-stone-100">
                <div class="h-full rounded bg-[#6f9b58]" :style="{ width: `${calorieProgress}%` }" />
            </div>
            <div class="grid grid-cols-3 mt-7 gap-5">
                <div
                    v-for="macro in [
                    ['Protein', summary.totals.protein_g, summary.goal?.protein_g, summary.totals.protein_remaining],
                    ['Carbs', summary.totals.carbs_g, summary.goal?.carbs_g, summary.totals.carbs_remaining],
                    ['Fat', summary.totals.fat_g, summary.goal?.fat_g, summary.totals.fat_remaining],
                ]"
                    :key="macro[0]"
                >
                    <p class="text-xs font-bold uppercase text-stone-500">{{ macro[0] }}</p>
                    <p class="mt-2 text-xl font-bold">{{ Math.round(macro[3] ?? 0) }}g</p>
                    <p class="text-xs font-semibold text-stone-500">left</p>
                    <div class="mt-3 h-2 overflow-hidden rounded bg-stone-100">
                        <div class="h-full rounded bg-[#d28a45]" :style="{ width: `${macroProgress(macro[1], macro[2])}%` }" />
                    </div>
                </div>
            </div>
        </Card>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-bold">Meals</h2>
            </div>

            <Card v-for="mealType in mealTypes" :key="mealType">
                <h3 class="font-bold">{{ mealLabels[mealType] }}</h3>

                <div v-if="summary.entries[mealType]?.length" class="mt-3 divide-y divide-stone-100">
                    <div v-for="entry in summary.entries[mealType]" :key="entry.id" class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ entry.name }}</p>
                            <p class="text-sm text-stone-500">
                                {{ entry.calories }} kcal · P {{ entry.protein_g }}g · C {{ entry.carbs_g }}g · F {{ entry.fat_g }}g
                            </p>
                        </div>
                        <button class="rounded p-2 text-stone-400 active:bg-stone-100" aria-label="Remove meal" @click="removeEntry(entry.id)">
                            <Trash2 :size="18" />
                        </button>
                    </div>
                </div>

                <p v-else class="mt-3 text-sm text-stone-500">No entries yet.</p>
            </Card>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-bold">Workouts</h2>
            </div>

            <Card>
                <div v-if="healthConnectState.supported" class="mb-3 flex items-center gap-3 rounded-md border border-stone-200 bg-stone-50 p-3">
                    <div class="grid h-10 w-10 flex-none place-items-center rounded-md bg-[#253d2c] text-white">
                        <Link2 :size="18" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ healthConnectLabel }}</p>
                        <p class="truncate text-sm text-stone-500">{{ healthConnectDetail }}</p>
                    </div>
                    <button
                        v-if="healthConnectState.status === 'connected' || healthConnectState.status === 'sync_queued'"
                        class="grid h-10 w-10 place-items-center rounded-md border border-stone-200 bg-white text-stone-700 disabled:opacity-60"
                        :disabled="healthConnectLoading"
                        aria-label="Sync Health Connect"
                        @click="syncHealthConnect"
                    >
                        <RefreshCw :size="17" :class="{ 'animate-spin': healthConnectLoading }" />
                    </button>
                    <button
                        v-else
                        class="flex h-10 items-center rounded-md bg-[#253d2c] px-3 text-sm font-bold text-white disabled:opacity-60"
                        :disabled="healthConnectLoading || !healthConnectState.available"
                        @click="connectHealthConnect"
                    >
                        Connect
                    </button>
                </div>

                <div v-if="summary.workouts?.length" class="divide-y divide-stone-100">
                    <div v-for="workout in summary.workouts" :key="workout.id" class="flex items-center gap-3 py-3">
                        <div class="grid h-10 w-10 flex-none place-items-center rounded-md bg-[#dce8d4] text-[#253d2c]">
                            <Dumbbell :size="19" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ workout.title }}</p>
                            <p class="text-sm text-stone-500">
                                {{ workout.calories_burned }} kcal burned · {{ workout.logged_time }}<span v-if="workout.source_type === 'health_connect'"> · Health Connect</span>
                            </p>
                        </div>
                        <button class="rounded p-2 text-stone-400 active:bg-stone-100" aria-label="Remove workout" @click="removeWorkout(workout.id)">
                            <Trash2 :size="18" />
                        </button>
                    </div>
                </div>

                <p v-else class="text-sm text-stone-500">No workouts yet.</p>
            </Card>
        </section>
    </section>
</template>
