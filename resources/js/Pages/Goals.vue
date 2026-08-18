<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Minus, Plus } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import Card from '../Components/Card.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { gramsForSplit, hasValidFivePercentSplit, macroPresets, splitFromGrams, splitWithinGramBounds, type MacroSplit } from '../goalMacros';
import { hapticImpact } from '../haptics';

const calorieStep = 50;
const calorieMin = 1;
const calorieMax = 20000;
const presetLabels = ['Balanced', 'High protein', 'Higher fat'] as const;

const props = defineProps<{
    goal: { calories: number; protein_g: number; carbs_g: number; fat_g: number };
}>();

const form = useForm({
    calories: props.goal.calories,
    protein_g: props.goal.protein_g,
    carbs_g: props.goal.carbs_g,
    fat_g: props.goal.fat_g,
});

function matchesGrams(split: MacroSplit): boolean {
    const grams = gramsForSplit(Number(form.calories), split);

    return grams.protein.toFixed(2) === Number(form.protein_g).toFixed(2)
        && grams.carbs.toFixed(2) === Number(form.carbs_g).toFixed(2)
        && grams.fat.toFixed(2) === Number(form.fat_g).toFixed(2);
}

const activePreset = ref<number | null>(macroPresets.findIndex(matchesGrams));
if (activePreset.value === -1) activePreset.value = null;
const customSplit = ref(splitFromGrams(props.goal.calories, {
    protein: props.goal.protein_g,
    carbs: props.goal.carbs_g,
    fat: props.goal.fat_g,
}));
const activeSplit = computed<MacroSplit>(() => activePreset.value === null ? customSplit.value : macroPresets[activePreset.value]);
const generatedGrams = computed(() => gramsForSplit(Number(form.calories), activeSplit.value));
const selectedSplitWithinBounds = computed(() => splitWithinGramBounds(Number(form.calories), activeSplit.value));
const hasRepresentableSplit = computed(() => hasValidFivePercentSplit(Number(form.calories)));
const canDecreaseCalories = computed(() => Number(form.calories) > calorieMin);
const canIncreaseCalories = computed(() => Number(form.calories) < calorieMax);
const percentageOptions = Array.from({ length: 21 }, (_, index) => index * 5);
const customScrollerElements: Partial<Record<'protein' | 'carbs', HTMLElement>> = {};
const scrollerItemHeight = 40;
const macros = computed(() => [
    { key: 'protein' as const, label: 'Protein', percent: activeSplit.value.protein, grams: generatedGrams.value.protein, color: 'bg-protein' },
    { key: 'carbs' as const, label: 'Carbs', percent: activeSplit.value.carbs, grams: generatedGrams.value.carbs, color: 'bg-carbs' },
    { key: 'fat' as const, label: 'Fat', percent: activeSplit.value.fat, grams: generatedGrams.value.fat, color: 'bg-fat' },
]);

function applySplit(split: MacroSplit): void {
    const grams = gramsForSplit(Number(form.calories), split);
    form.protein_g = grams.protein;
    form.carbs_g = grams.carbs;
    form.fat_g = grams.fat;
}

function nudgeCalories(delta: number): void {
    hapticImpact();
    form.calories = Math.min(calorieMax, Math.max(calorieMin, Number(form.calories || 0) + delta));
}

function selectPreset(index: number): void {
    hapticImpact();
    activePreset.value = index;
    applySplit(macroPresets[index]);
}

function selectCustom(): void {
    hapticImpact();
    activePreset.value = null;
    nextTick(() => {
        scrollToCustom('protein', customSplit.value.protein);
        scrollToCustom('carbs', customSplit.value.carbs);
    });
}

function updateCustom(key: 'protein' | 'carbs', value: number): void {
    customSplit.value = key === 'protein'
        ? { protein: value, carbs: Math.min(customSplit.value.carbs, 100 - value), fat: 100 - value - Math.min(customSplit.value.carbs, 100 - value) }
        : { protein: customSplit.value.protein, carbs: Math.min(value, 100 - customSplit.value.protein), fat: 100 - customSplit.value.protein - Math.min(value, 100 - customSplit.value.protein) };
    activePreset.value = null;
    applySplit(customSplit.value);
}

function customOptions(key: 'protein' | 'carbs'): number[] {
    return percentageOptions.filter((percent) => percent <= 100 - (key === 'protein' ? customSplit.value.carbs : customSplit.value.protein));
}

function setCustomScrollerRef(key: 'protein' | 'carbs', element: unknown): void {
    if (element instanceof HTMLElement) {
        customScrollerElements[key] = element;
    }
}

function scrollToCustom(key: 'protein' | 'carbs', percent: number): void {
    const index = customOptions(key).indexOf(percent);
    customScrollerElements[key]?.scrollTo({ top: Math.max(0, index) * scrollerItemHeight, behavior: 'smooth' });
}

function handleCustomScroller(key: 'protein' | 'carbs', event: Event): void {
    const options = customOptions(key);
    const index = Math.max(0, Math.min(options.length - 1, Math.round((event.currentTarget as HTMLElement).scrollTop / scrollerItemHeight)));
    updateCustom(key, options[index]);
}

function save(): void {
    form.put('/goals', { preserveScroll: true });
}

watch(() => form.calories, () => applySplit(activeSplit.value));
</script>

<template>
    <Head title="Goals" />
    <section class="space-y-5">
        <PageHeader>Goals</PageHeader>
        <form class="space-y-4" @submit.prevent="save">
            <Card>
                <div class="flex items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="rounded-full"
                        :disabled="!canDecreaseCalories"
                        aria-label="Decrease calories by 50"
                        @click="nudgeCalories(-calorieStep)"
                    >
                        <Minus :size="20" />
                    </Button>
                    <label class="min-w-0 flex-1 text-center">
                        <span class="field-label">Calories</span>
                        <Input
                            v-model.number="form.calories"
                            type="number"
                            :min="calorieMin"
                            :max="calorieMax"
                            class="mt-1 border-0 bg-transparent px-0 text-center text-[2.35rem] font-semibold leading-none tracking-tight tabular-nums shadow-none [appearance:textfield] focus:border-transparent focus:bg-transparent focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                        />
                        <span class="mt-1 block text-sm text-muted-foreground">kcal per day</span>
                    </label>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="rounded-full"
                        :disabled="!canIncreaseCalories"
                        aria-label="Increase calories by 50"
                        @click="nudgeCalories(calorieStep)"
                    >
                        <Plus :size="20" />
                    </Button>
                </div>
                <p v-if="form.errors.calories" class="mt-2 text-center text-sm text-destructive">{{ form.errors.calories }}</p>

                <div
                    class="mt-5 flex h-2.5 overflow-hidden rounded-full bg-muted"
                    role="img"
                    :aria-label="`${activeSplit.protein}% protein, ${activeSplit.carbs}% carbs, ${activeSplit.fat}% fat`"
                >
                    <div
                        v-for="macro in macros"
                        :key="macro.key"
                        class="h-full transition-[width] duration-200 ease-out motion-reduce:transition-none"
                        :class="macro.color"
                        :style="{ width: `${macro.percent}%` }"
                    />
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    <div v-for="macro in macros" :key="macro.key" class="rounded-xl bg-muted p-3 text-center">
                        <p class="field-label">{{ macro.label }}</p>
                        <p class="mt-1 text-lg font-semibold tracking-tight tabular-nums">{{ Math.round(macro.grams) }}g</p>
                        <p class="text-xs text-muted-foreground">{{ macro.percent }}%</p>
                    </div>
                </div>
            </Card>

            <Card class="space-y-4">
                <div>
                    <h2 class="font-semibold tracking-tight">Macro split</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Protein / carbs / fat from your calorie target.</p>
                </div>

                <div class="divide-y divide-border/60 overflow-hidden rounded-xl border border-border/80 bg-muted/50">
                    <Button
                        v-for="(preset, index) in macroPresets"
                        :key="index"
                        type="button"
                        variant="ghost"
                        class="h-auto w-full justify-between gap-3 rounded-none px-3 py-3 text-left"
                        :class="activePreset === index ? 'bg-secondary text-foreground' : 'text-foreground'"
                        :aria-pressed="activePreset === index"
                        :disabled="!splitWithinGramBounds(Number(form.calories), preset)"
                        @click="selectPreset(index)"
                    >
                        <span class="min-w-0">
                            <span class="block font-semibold">{{ presetLabels[index] }}</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">{{ preset.protein }} / {{ preset.carbs }} / {{ preset.fat }}</span>
                        </span>
                        <span class="flex h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-card" aria-hidden="true">
                            <span class="h-full bg-protein" :style="{ width: `${preset.protein}%` }" />
                            <span class="h-full bg-carbs" :style="{ width: `${preset.carbs}%` }" />
                            <span class="h-full bg-fat" :style="{ width: `${preset.fat}%` }" />
                        </span>
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-auto w-full justify-between gap-3 rounded-none px-3 py-3 text-left"
                        :class="activePreset === null ? 'bg-secondary text-foreground' : 'text-foreground'"
                        :aria-pressed="activePreset === null"
                        @click="selectCustom"
                    >
                        <span class="min-w-0">
                            <span class="block font-semibold">Custom</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">Set protein and carbs in 5% steps</span>
                        </span>
                    </Button>
                </div>

                <div v-if="activePreset === null" class="rounded-xl border border-border/60 bg-muted p-3">
                    <div class="mb-2 grid grid-cols-3 text-center">
                        <span class="field-label">Protein</span>
                        <span class="field-label">Carbs</span>
                        <span class="field-label">Fat</span>
                    </div>
                    <div class="relative grid grid-cols-3 -mb-3 -mx-3">
                        <div class="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-10 -translate-y-1/2 border-y border-border bg-card/75" />
                        <div
                            v-for="key in ['protein', 'carbs'] as const"
                            :key="key"
                            :ref="(element) => setCustomScrollerRef(key, element)"
                            class="macro-wheel h-40 snap-y snap-mandatory overflow-y-auto py-[60px] text-center"
                            @scroll.passive="handleCustomScroller(key, $event)"
                        >
                            <Button
                                v-for="percent in customOptions(key)"
                                :key="percent"
                                type="button"
                                variant="ghost"
                                class="relative z-20 h-10 w-full snap-center text-xl tabular-nums"
                                :class="customSplit[key] === percent ? (key === 'protein' ? 'text-protein' : 'text-carbs') : 'text-muted-foreground/40'"
                                @click="updateCustom(key, percent); scrollToCustom(key, percent)"
                            >
                                {{ percent }}<span v-if="customSplit[key] === percent" class="ml-1 text-sm">%</span>
                            </Button>
                        </div>
                        <div class="grid place-items-center tabular-nums">{{ customSplit.fat }}%</div>
                    </div>
                </div>

                <p v-if="!hasRepresentableSplit" class="text-sm text-destructive">This calorie target cannot be represented with 5% macro steps.</p>
                <p v-else-if="!selectedSplitWithinBounds" class="text-sm text-destructive">Adjust calories or the macro split; each macro must be 1000g or less.</p>
                <p v-for="field in ['protein_g', 'carbs_g', 'fat_g']" :key="field" v-show="form.errors[field]" class="text-sm text-destructive">{{ form.errors[field] }}</p>
            </Card>
            <Button class="w-full" size="lg" :disabled="form.processing || !hasRepresentableSplit || !selectedSplitWithinBounds">Save goals</Button>
        </form>
    </section>
</template>

<style scoped>
.macro-wheel { scrollbar-width: none; }
.macro-wheel::-webkit-scrollbar { display: none; }
</style>
