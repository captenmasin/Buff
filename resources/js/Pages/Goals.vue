<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    goal: { type: Object, required: true },
});

const macroFields = [
    { key: 'carbs_g', label: 'Carbs', multiplier: 4 },
    { key: 'protein_g', label: 'Protein', multiplier: 4 },
    { key: 'fat_g', label: 'Fat', multiplier: 9 },
];

const percentageOptions = Array.from({ length: 21 }, (_, index) => index * 5);
const scrollerItemHeight = 40;
const macroScrollerElements = {};

const form = useForm({
    calories: props.goal.calories,
    protein_g: props.goal.protein_g,
    carbs_g: props.goal.carbs_g,
    fat_g: props.goal.fat_g,
});

const macroCalories = computed(() => {
    return Math.round((Number(form.protein_g) * 4) + (Number(form.carbs_g) * 4) + (Number(form.fat_g) * 9));
});

const selectedMacroPercents = ref(initialMacroPercents());
const macroPercentTotal = computed(() => {
    return macroFields.reduce((total, field) => total + Number(selectedMacroPercents.value[field.key]), 0);
});

const matchesGoal = computed(() => {
    return macroPercentTotal.value === 100 && macroCalories.value === Math.round(Number(form.calories));
});

function clampPercent(value) {
    return Math.max(0, Math.min(100, value));
}

function percentFromGrams(value, multiplier) {
    if (!Number(form.calories)) return 0;

    const calories = Number(value) * multiplier;

    return clampPercent(Math.round((calories / Number(form.calories)) * 20) * 5);
}

function initialMacroPercents() {
    const percents = Object.fromEntries(
        macroFields.map((field) => [field.key, percentFromGrams(props.goal[field.key], field.multiplier)])
    );
    const total = macroFields.reduce((sum, field) => sum + percents[field.key], 0);

    if (total !== 100) {
        const largestField = macroFields.reduce((largest, field) => {
            return Number(props.goal[field.key]) > Number(props.goal[largest.key]) ? field : largest;
        }, macroFields[0]);

        percents[largestField.key] = clampPercent(percents[largestField.key] + (100 - total));
    }

    return percents;
}

function gramsForPercent(percent, multiplier) {
    if (!Number(form.calories)) return 0;

    return Number((((Number(form.calories) * Number(percent)) / 100) / multiplier).toFixed(1));
}

function syncFormFromPercentages() {
    macroFields.forEach((field) => {
        form[field.key] = gramsForPercent(selectedMacroPercents.value[field.key], field.multiplier);
    });
}

function setScrollerRef(key, element) {
    if (element) {
        macroScrollerElements[key] = element;

        return;
    }

    delete macroScrollerElements[key];
}

function scrollToPercent(key, percent, behavior = 'smooth') {
    nextTick(() => {
        const element = macroScrollerElements[key];
        const index = percentageOptions.indexOf(Number(percent));

        if (!element || index === -1) return;

        element.scrollTo({
            top: index * scrollerItemHeight,
            behavior: behavior === 'smooth' ? 'smooth' : 'auto',
        });
    });
}

function selectMacroPercent(field, percent, shouldScroll = true) {
    selectedMacroPercents.value[field.key] = Number(percent);
    syncFormFromPercentages();

    if (shouldScroll) {
        scrollToPercent(field.key, percent);
    }
}

function handleScrollerScroll(field, event) {
    const index = Math.max(0, Math.min(percentageOptions.length - 1, Math.round(event.target.scrollTop / scrollerItemHeight)));

    selectMacroPercent(field, percentageOptions[index], false);
}

function save() {
    form.put('/goals', { preserveScroll: true });
}

watch(() => form.calories, () => {
    syncFormFromPercentages();
});

onMounted(() => {
    syncFormFromPercentages();

    macroFields.forEach((field) => {
        scrollToPercent(field.key, selectedMacroPercents.value[field.key], 'auto');
    });
});
</script>

<template>
    <Head title="Goals" />

    <section class="space-y-5">
        <header>
            <p class="text-sm font-semibold text-stone-500">Daily target</p>
            <h1 class="text-3xl font-bold tracking-normal text-[#17211b]">Goals</h1>
        </header>

        <form class="space-y-4" @submit.prevent="save">
            <article class="rounded-md border border-stone-200 bg-white p-4 shadow-sm">
                <label>
                    <span class="text-xs font-bold uppercase text-stone-500">Calories</span>
                    <input
                        v-model.number="form.calories"
                        type="number"
                        min="1"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-2xl font-bold outline-none focus:border-[#6f9b58]"
                    >
                </label>
                <p v-if="form.errors.calories" class="mt-1 text-sm font-semibold text-red-700">{{ form.errors.calories }}</p>
            </article>

            <article class="rounded-md border border-stone-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Macros</h2>
                        <p class="text-sm text-stone-500">{{ macroCalories }} kcal from macros</p>
                    </div>
                    <span
                        class="rounded-md px-3 py-2 text-xs font-bold"
                        :class="matchesGoal ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'"
                    >
                        {{ matchesGoal ? 'Matches' : 'Mismatch' }}
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div v-for="macro in macroFields" :key="macro.key">
                        <p class="text-base font-bold text-stone-600">{{ macro.label }}</p>
                        <p class="mt-0.5 text-xs font-semibold text-stone-400">{{ form[macro.key] }} g</p>
                    </div>
                </div>

                <div class="isolate relative mt-4 overflow-hidden rounded-md border border-stone-100 bg-stone-50">
                    <div class="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-10 -translate-y-1/2 border-y border-stone-200 bg-white/95" />
                    <div class="pointer-events-none absolute inset-x-0 top-0 z-20 h-12 bg-gradient-to-b from-stone-50 to-stone-50/0" />
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-12 bg-gradient-to-t from-stone-50 to-stone-50/0" />

                    <div class="grid grid-cols-3 divide-x divide-stone-100">
                        <div
                            v-for="macro in macroFields"
                            :key="macro.key"
                            :ref="(element) => setScrollerRef(macro.key, element)"
                            class="macro-wheel h-40 snap-y snap-mandatory overflow-y-auto py-[60px]"
                            @scroll.passive="handleScrollerScroll(macro, $event)"
                        >
                            <button
                                v-for="percentOption in percentageOptions"
                                :key="percentOption"
                                type="button"
                                class="relative z-10 flex h-10 w-full snap-center items-center justify-center text-xl font-bold transition"
                                :class="selectedMacroPercents[macro.key] === percentOption ? 'text-[#253d2c]' : 'text-stone-300'"
                                @click="selectMacroPercent(macro, percentOption)"
                            >
                                {{ percentOption }}
                                <span v-if="selectedMacroPercents[macro.key] === percentOption" class="ml-1 text-base">%</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-base font-bold text-stone-500">% Total</p>
                        <p class="text-xs font-semibold text-stone-500">Macronutrients must equal 100%</p>
                    </div>
                    <p
                        class="text-2xl font-bold"
                        :class="macroPercentTotal === 100 ? 'text-[#a8cf3a]' : 'text-red-700'"
                    >
                        {{ macroPercentTotal }}%
                    </p>
                </div>

                <p v-for="macro in macroFields" :key="`${macro.key}-error`" v-show="form.errors[macro.key]" class="mt-2 text-sm font-semibold text-red-700">
                    {{ form.errors[macro.key] }}
                </p>
            </article>

            <button
                class="w-full rounded-md bg-[#253d2c] px-4 py-4 text-base font-bold text-white active:bg-[#17211b] disabled:cursor-not-allowed disabled:bg-stone-300"
                :disabled="form.processing || !matchesGoal"
            >
                Save goals
            </button>
        </form>
    </section>
</template>

<style scoped>
.macro-wheel {
    scrollbar-width: none;
}

.macro-wheel::-webkit-scrollbar {
    display: none;
}
</style>
