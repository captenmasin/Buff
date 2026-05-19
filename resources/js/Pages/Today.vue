<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Flame, Trash2 } from '@lucide/vue';
import { formatDisplayDate } from '../dateFormat';

const props = defineProps({
    summary: { type: Object, required: true },
    week: { type: Array, required: true },
    mealTypes: { type: Array, required: true },
});

const mealLabels = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

const burnedForm = useForm({
    date: props.summary.date,
    burned_calories: props.summary.log.burned_calories,
});

const hasGoal = computed(() => Boolean(props.summary.goal));
const displayDate = computed(() => formatDisplayDate(props.summary.date));
const calorieProgress = computed(() => {
    if (!hasGoal.value || props.summary.goal.calories === 0) return 0;
    return Math.min(100, Math.round((props.summary.totals.calories / props.summary.goal.calories) * 100));
});

function macroProgress(consumed, goal) {
    if (!goal) return 0;
    return Math.min(100, Math.round((consumed / goal) * 100));
}

function saveBurned() {
    burnedForm.put('/burned-calories', { preserveScroll: true });
}

function removeEntry(id) {
    router.delete(`/meals/${id}`, { preserveScroll: true });
}

function dayStatusClass(status) {
    return {
        target: 'bg-emerald-500',
        under: 'bg-amber-400',
        over: 'bg-red-500',
        neutral: 'bg-stone-300',
    }[status] || 'bg-stone-300';
}
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

        <nav class="grid grid-cols-7 gap-2 rounded-md border border-stone-200 bg-white p-2 shadow-sm" aria-label="Week">
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

        <article class="rounded-md border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-stone-500">Calories left</p>
                    <div class="mt-1 flex items-end gap-2">
                        <span class="text-4xl font-bold">{{ summary.totals.calories_remaining }}</span>
                        <span class="pb-1 text-sm font-semibold text-stone-500">kcal</span>
                    </div>
                </div>
                <div class="rounded-md bg-[#253d2c] px-3 py-2 text-right text-white">
                    <p class="text-xs font-semibold text-white/70">Eaten</p>
                    <p class="text-lg font-bold">{{ summary.totals.calories }}</p>
                </div>
            </div>

            <div class="mt-4 h-3 overflow-hidden rounded bg-stone-100">
                <div class="h-full rounded bg-[#6f9b58]" :style="{ width: `${calorieProgress}%` }" />
            </div>

            <form class="mt-4 flex items-end gap-2" @submit.prevent="saveBurned">
                <label class="flex-1">
                    <span class="text-xs font-bold uppercase text-stone-500">Burned today</span>
                    <input
                        v-model="burnedForm.burned_calories"
                        type="number"
                        min="0"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-base font-semibold outline-none focus:border-[#6f9b58]"
                    >
                </label>
                <button class="flex h-12 items-center gap-2 rounded-md bg-[#253d2c] px-4 text-sm font-bold text-white active:bg-[#17211b]" :disabled="burnedForm.processing">
                    <Flame :size="18" />
                    Save
                </button>
            </form>
        </article>

        <article class="grid grid-cols-3 gap-3">
            <div
                v-for="macro in [
                    ['Protein', summary.totals.protein_g, summary.goal?.protein_g, summary.totals.protein_remaining],
                    ['Carbs', summary.totals.carbs_g, summary.goal?.carbs_g, summary.totals.carbs_remaining],
                    ['Fat', summary.totals.fat_g, summary.goal?.fat_g, summary.totals.fat_remaining],
                ]"
                :key="macro[0]"
                class="rounded-md border border-stone-200 bg-white p-3 shadow-sm"
            >
                <p class="text-xs font-bold uppercase text-stone-500">{{ macro[0] }}</p>
                <p class="mt-2 text-xl font-bold">{{ Math.round(macro[3] ?? 0) }}g</p>
                <p class="text-xs font-semibold text-stone-500">left</p>
                <div class="mt-3 h-2 overflow-hidden rounded bg-stone-100">
                    <div class="h-full rounded bg-[#d28a45]" :style="{ width: `${macroProgress(macro[1], macro[2])}%` }" />
                </div>
            </div>
        </article>

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-bold">Meals</h2>
            </div>

            <article v-for="mealType in mealTypes" :key="mealType" class="rounded-md border border-stone-200 bg-white p-4 shadow-sm">
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
            </article>
        </section>
    </section>
</template>
