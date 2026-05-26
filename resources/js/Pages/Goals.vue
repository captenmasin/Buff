<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Card from "../Components/Card.vue";
import Badge from '../Components/ui/badge/Badge.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';

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
            <p class="text-sm  text-muted-foreground">Daily target</p>
            <h1 class="text-3xl font-semibold tracking-normal text-foreground">Goals</h1>
        </header>

        <form class="space-y-4" @submit.prevent="save">
            <Card>
                <label>
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Calories</span>
                    <Input
                        v-model.number="form.calories"
                        type="number"
                        min="1"
                        class="mt-1 text-2xl font-semibold"
                    />
                </label>
                <p v-if="form.errors.calories" class="mt-1 text-sm  text-destructive">{{ form.errors.calories }}</p>
            </Card>

            <Card>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Macros</h2>
                        <p class="text-sm text-muted-foreground">{{ macroCalories }} kcal from macros</p>
                    </div>
                    <Badge :variant="matchesGoal ? 'success' : 'destructive'">
                        {{ matchesGoal ? 'Matches' : 'Mismatch' }}
                    </Badge>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div v-for="macro in macroFields" :key="macro.key">
                        <p class="text-base font-semibold text-muted-foreground">{{ macro.label }}</p>
                        <p class="mt-0.5 text-xs  text-muted-foreground/70">{{ form[macro.key] }} g</p>
                    </div>
                </div>

                <div class="isolate relative mt-4 overflow-hidden rounded-md border border-border/60 bg-muted">
                    <div class="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-10 -translate-y-1/2 border-y border-border bg-card/95" />
                    <div class="pointer-events-none absolute inset-x-0 top-0 z-20 h-12 bg-gradient-to-b from-muted to-muted/0" />
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-12 bg-gradient-to-t from-muted to-muted/0" />

                    <div class="grid grid-cols-3 divide-x divide-border/70">
                        <div
                            v-for="macro in macroFields"
                            :key="macro.key"
                            :ref="(element) => setScrollerRef(macro.key, element)"
                            class="macro-wheel h-40 snap-y snap-mandatory overflow-y-auto py-[60px]"
                            @scroll.passive="handleScrollerScroll(macro, $event)"
                        >
                            <Button
                                v-for="percentOption in percentageOptions"
                                :key="percentOption"
                                type="button"
                                variant="ghost"
                                class="relative z-10 h-10 w-full snap-center text-xl"
                                :class="selectedMacroPercents[macro.key] === percentOption ? 'text-primary' : 'text-muted-foreground/40'"
                                @click="selectMacroPercent(macro, percentOption)"
                            >
                                {{ percentOption }}
                                <span v-if="selectedMacroPercents[macro.key] === percentOption" class="ml-1 text-base">%</span>
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-base font-semibold text-muted-foreground">% Total</p>
                        <p class="text-xs  text-muted-foreground">Macronutrients must equal 100%</p>
                    </div>
                    <p
                        class="text-2xl font-semibold"
                        :class="macroPercentTotal === 100 ? 'text-success' : 'text-destructive'"
                    >
                        {{ macroPercentTotal }}%
                    </p>
                </div>

                <p v-for="macro in macroFields" :key="`${macro.key}-error`" v-show="form.errors[macro.key]" class="mt-2 text-sm  text-destructive">
                    {{ form.errors[macro.key] }}
                </p>
            </Card>

            <Button
                class="w-full"
                size="lg"
                :disabled="form.processing || !matchesGoal"
            >
                Save goals
            </Button>
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
