<script setup lang="ts">
import { Check, Minus, Plus } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import Card from './Card.vue';
import Button from './ui/button/Button.vue';
import { NumberField, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from './ui/number-field';
import { gramsForSplit, hasValidFivePercentSplit, macroPresets, splitFromGrams, splitWithinGramBounds, type MacroSplit } from '../goalMacros';
import { hapticImpact } from '../haptics';
import { cn } from '../lib/utils';

const calorieStep = 50;
const calorieMin = 1;
const calorieMax = 20000;
const presetLabels = ['Balanced', 'High protein', 'Higher fat'] as const;

const calories = defineModel<number>('calories', { required: true });
const proteinG = defineModel<number>('protein_g', { required: true });
const carbsG = defineModel<number>('carbs_g', { required: true });
const fatG = defineModel<number>('fat_g', { required: true });

const props = defineProps<{
    errors?: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    valid: [value: boolean];
}>();

function matchesGrams(split: MacroSplit): boolean {
    const grams = gramsForSplit(Number(calories.value), split);

    return grams.protein.toFixed(2) === Number(proteinG.value).toFixed(2)
        && grams.carbs.toFixed(2) === Number(carbsG.value).toFixed(2)
        && grams.fat.toFixed(2) === Number(fatG.value).toFixed(2);
}

const activePreset = ref<number | null>(macroPresets.findIndex(matchesGrams));
if (activePreset.value === -1) {
    activePreset.value = null;
}
const customSplit = ref(splitFromGrams(Number(calories.value), {
    protein: Number(proteinG.value),
    carbs: Number(carbsG.value),
    fat: Number(fatG.value),
}));
const activeSplit = computed<MacroSplit>(() => activePreset.value === null ? customSplit.value : macroPresets[activePreset.value]);
const generatedGrams = computed(() => gramsForSplit(Number(calories.value), activeSplit.value));
const selectedSplitWithinBounds = computed(() => splitWithinGramBounds(Number(calories.value), activeSplit.value));
const hasRepresentableSplit = computed(() => hasValidFivePercentSplit(Number(calories.value)));
const canSave = computed(() => hasRepresentableSplit.value && selectedSplitWithinBounds.value);
const percentageOptions = Array.from({ length: 21 }, (_, index) => index * 5);
const customScrollerElements: Partial<Record<'protein' | 'carbs', HTMLElement>> = {};
const scrollerItemHeight = 40;
const pickerColumns = [
    { key: 'protein', color: 'text-protein', scrollable: true },
    { key: 'carbs', color: 'text-carbs', scrollable: true },
    { key: 'fat', color: 'text-fat', scrollable: false },
] as const;
const macros = computed(() => [
    { key: 'protein' as const, label: 'Protein', percent: activeSplit.value.protein, grams: generatedGrams.value.protein, color: 'bg-protein' },
    { key: 'carbs' as const, label: 'Carbs', percent: activeSplit.value.carbs, grams: generatedGrams.value.carbs, color: 'bg-carbs' },
    { key: 'fat' as const, label: 'Fat', percent: activeSplit.value.fat, grams: generatedGrams.value.fat, color: 'bg-fat' },
]);

function applySplit(split: MacroSplit): void {
    const grams = gramsForSplit(Number(calories.value), split);
    proteinG.value = grams.protein;
    carbsG.value = grams.carbs;
    fatG.value = grams.fat;
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

function updateCustom(key: 'protein' | 'carbs' | 'fat', value: number): void {
    if (key === 'fat') {
        return;
    }

    customSplit.value = key === 'protein'
        ? { protein: value, carbs: Math.min(customSplit.value.carbs, 100 - value), fat: 100 - value - Math.min(customSplit.value.carbs, 100 - value) }
        : { protein: customSplit.value.protein, carbs: Math.min(value, 100 - customSplit.value.protein), fat: 100 - customSplit.value.protein - Math.min(value, 100 - customSplit.value.protein) };
    activePreset.value = null;
    applySplit(customSplit.value);
}

function customOptions(key: 'protein' | 'carbs' | 'fat'): number[] {
    if (key === 'fat') {
        return [];
    }

    return percentageOptions.filter((percent) => percent <= 100 - (key === 'protein' ? customSplit.value.carbs : customSplit.value.protein));
}

function setCustomScrollerRef(key: 'protein' | 'carbs', element: unknown): void {
    if (element instanceof HTMLElement) {
        customScrollerElements[key] = element;
    }
}

function bindPickerScroller(key: (typeof pickerColumns)[number]['key'], element: unknown): void {
    if (key !== 'fat') {
        setCustomScrollerRef(key, element);
    }
}

function scrollToCustom(key: 'protein' | 'carbs' | 'fat', percent: number): void {
    if (key === 'fat') {
        return;
    }

    const index = customOptions(key).indexOf(percent);
    customScrollerElements[key]?.scrollTo({ top: Math.max(0, index) * scrollerItemHeight, behavior: 'smooth' });
}

function handleCustomScroller(key: 'protein' | 'carbs' | 'fat', event: Event): void {
    if (key === 'fat') {
        return;
    }

    const options = customOptions(key);
    const index = Math.max(0, Math.min(options.length - 1, Math.round((event.currentTarget as HTMLElement).scrollTop / scrollerItemHeight)));
    updateCustom(key, options[index]);
}

watch(calories, (value, previous) => {
    if (Math.abs(Number(value) - Number(previous)) === calorieStep) {
        hapticImpact();
    }

    applySplit(activeSplit.value);
});

watch(canSave, (value) => emit('valid', value), { immediate: true });
</script>

<template>
    <div class="space-y-4">
        <Card>
            <NumberField
                v-model="calories"
                :min="calorieMin"
                :max="calorieMax"
                :step="calorieStep"
                :step-snapping="false"
                :focus-on-change="false"
                disable-wheel-change
                :format-options="{ useGrouping: false, maximumFractionDigits: 0 }"
                class="flex items-center gap-3"
            >
                <NumberFieldDecrement as-child class="relative top-auto left-auto translate-y-0 p-0">
                    <Button type="button" variant="outline" size="icon" class="rounded-full" aria-label="Decrease calories by 50">
                        <Minus :size="20" />
                    </Button>
                </NumberFieldDecrement>
                <label class="min-w-0 flex-1 text-center">
                    <span class="field-label">Calories</span>
                    <NumberFieldInput
                        :class="cn('mt-1 h-auto border-0 bg-transparent px-0 py-1 text-center text-5xl font-bold leading-none tracking-tight tabular-nums shadow-none md:text-5xl focus:border-transparent focus:bg-transparent focus-visible:ring-2 focus-visible:ring-ring')"
                    />
                    <span class="mt-1 block text-sm text-muted-foreground">kcal per day</span>
                </label>
                <NumberFieldIncrement as-child class="relative top-auto right-auto translate-y-0 p-0">
                    <Button type="button" variant="outline" size="icon" class="rounded-full" aria-label="Increase calories by 50">
                        <Plus :size="20" />
                    </Button>
                </NumberFieldIncrement>
            </NumberField>
            <p v-if="props.errors?.calories" class="mt-2 text-center text-sm text-destructive">{{ props.errors.calories }}</p>

            <div
                class="mt-5 flex h-2.5 overflow-hidden rounded-full bg-muted"
                role="img"
                :aria-label="`${activeSplit.protein}% protein, ${activeSplit.carbs}% carbs, ${activeSplit.fat}% fat`"
            >
                <div
                    v-for="macro in macros"
                    :key="macro.key"
                    class="h-full"
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
                <h2 class="card-title">Macro split</h2>
                <p class="mt-1 text-sm text-muted-foreground">How calories divide across protein, carbs, and fat.</p>
            </div>

            <div class="space-y-2" role="radiogroup" aria-label="Macro split">
                <button
                    v-for="(preset, index) in macroPresets"
                    :key="index"
                    type="button"
                    role="radio"
                    class="w-full rounded-xl border px-3 py-3 text-left transition-[color,background-color,border-color,box-shadow] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card disabled:pointer-events-none disabled:opacity-40"
                    :class="activePreset === index ? 'border-ring bg-primary/20 shadow-sm ring-1 ring-ring' : 'border-border bg-card hover:bg-muted/60'"
                    :aria-checked="activePreset === index"
                    :aria-label="`${presetLabels[index]}, ${preset.protein}% protein, ${preset.carbs}% carbs, ${preset.fat}% fat`"
                    :disabled="!splitWithinGramBounds(Number(calories), preset)"
                    @click="selectPreset(index)"
                >
                    <span class="flex items-center justify-between gap-2">
                        <span class="truncate font-semibold">{{ presetLabels[index] }}</span>
                        <Check
                            v-if="activePreset === index"
                            :size="18"
                            stroke-width="3"
                            class="shrink-0 rounded-full bg-primary p-0.5"
                        />
                    </span>
                    <span class="mt-2 flex h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                        <span class="h-full bg-protein" :style="{ width: `${preset.protein}%` }" />
                        <span class="h-full bg-carbs" :style="{ width: `${preset.carbs}%` }" />
                        <span class="h-full bg-fat" :style="{ width: `${preset.fat}%` }" />
                    </span>
                    <span class="mt-1.5 flex items-center justify-between gap-2 text-xs font-medium tabular-nums" aria-hidden="true">
                        <span class="text-protein">Protein {{ preset.protein }}%</span>
                        <span class="text-carbs">Carbs {{ preset.carbs }}%</span>
                        <span class="text-fat">Fat {{ preset.fat }}%</span>
                    </span>
                </button>
                <button
                    type="button"
                    role="radio"
                    class="w-full rounded-xl border px-3 py-3 text-left transition-[color,background-color,border-color,box-shadow] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card"
                    :class="activePreset === null ? 'border-ring bg-primary/20 shadow-sm ring-1 ring-ring' : 'border-border bg-card hover:bg-muted/60'"
                    :aria-checked="activePreset === null"
                    :aria-label="`Custom, ${customSplit.protein}% protein, ${customSplit.carbs}% carbs, ${customSplit.fat}% fat`"
                    @click="selectCustom"
                >
                    <span class="flex items-center justify-between gap-2">
                        <span class="truncate font-semibold">Custom</span>
                        <Check
                            v-if="activePreset === null"
                            :size="18"
                            stroke-width="3"
                            class="shrink-0 rounded-full bg-primary p-0.5"
                        />
                    </span>
                    <span class="mt-2 flex h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                        <span class="h-full bg-protein" :style="{ width: `${customSplit.protein}%` }" />
                        <span class="h-full bg-carbs" :style="{ width: `${customSplit.carbs}%` }" />
                        <span class="h-full bg-fat" :style="{ width: `${customSplit.fat}%` }" />
                    </span>
                    <span class="mt-1.5 flex items-center justify-between gap-2 text-xs font-medium tabular-nums" aria-hidden="true">
                        <span class="text-protein">Protein {{ customSplit.protein }}%</span>
                        <span class="text-carbs">Carbs {{ customSplit.carbs }}%</span>
                        <span class="text-fat">Fat {{ customSplit.fat }}%</span>
                    </span>
                </button>
            </div>

            <div v-if="activePreset === null" class="overflow-hidden rounded-xl bg-muted">
                <div class="grid grid-cols-3 px-2 pt-3 text-center">
                    <span class="macro-value inline-flex items-center justify-center gap-1.5 text-muted-foreground">
                        <span class="size-1.5 rounded-full bg-protein" aria-hidden="true" />
                        Protein
                    </span>
                    <span class="macro-value inline-flex items-center justify-center gap-1.5 text-muted-foreground">
                        <span class="size-1.5 rounded-full bg-carbs" aria-hidden="true" />
                        Carbs
                    </span>
                    <span class="macro-value inline-flex items-center justify-center gap-1.5 text-muted-foreground">
                        <span class="size-1.5 rounded-full bg-fat" aria-hidden="true" />
                        Fat
                    </span>
                </div>
                <div class="relative grid grid-cols-3">
                    <div class="pointer-events-none absolute inset-x-2 top-1/2 z-0 h-10 -translate-y-1/2 rounded-lg bg-card shadow-sm ring-1 ring-border/70" />
                    <div
                        v-for="column in pickerColumns"
                        :key="column.key"
                        class="relative z-10"
                    >
                        <div
                            v-if="column.scrollable"
                            :ref="(element) => bindPickerScroller(column.key, element)"
                            class="macro-wheel h-40 snap-y snap-mandatory overflow-y-auto py-[60px] text-center"
                            :aria-label="`${column.key} percent`"
                            @scroll.passive="handleCustomScroller(column.key, $event)"
                        >
                            <button
                                v-for="percent in customOptions(column.key)"
                                :key="percent"
                                type="button"
                                class="flex h-10 w-full appearance-none snap-center items-center justify-center bg-transparent p-0 font-normal"
                                :class="customSplit[column.key] === percent ? column.color : 'text-muted-foreground/35'"
                                @click="updateCustom(column.key, percent); scrollToCustom(column.key, percent)"
                            >
                                <span class="macro-value w-[3ch] text-right">{{ percent }}</span>
                                <span class="w-5" aria-hidden="true" />
                            </button>
                        </div>
                        <div
                            v-else
                            class="macro-value flex h-40 items-center justify-center"
                            :class="column.color"
                        >
                            <span class="macro-value w-[3ch] text-right">{{ customSplit.fat }}</span>
                            <span class="w-5" aria-hidden="true" />
                        </div>
                        <span
                            class="macro-value pointer-events-none absolute inset-0 flex items-center justify-center"
                            :class="column.color"
                            aria-hidden="true"
                        >
                            <span class="w-[3ch]" />
                            <span class="w-5">%</span>
                        </span>
                    </div>
                    <div class="pointer-events-none absolute inset-x-0 top-0 z-20 h-14 bg-gradient-to-b from-muted to-transparent" />
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-20 h-14 bg-gradient-to-t from-muted to-transparent" />
                </div>
            </div>

            <p v-if="!hasRepresentableSplit" class="text-sm text-destructive">This calorie target cannot be represented with 5% macro steps.</p>
            <p v-else-if="!selectedSplitWithinBounds" class="text-sm text-destructive">Adjust calories or the macro split; each macro must be 1000g or less.</p>
            <p v-for="field in ['protein_g', 'carbs_g', 'fat_g']" :key="field" v-show="props.errors?.[field]" class="text-sm text-destructive">{{ props.errors?.[field] }}</p>
        </Card>
    </div>
</template>

<style scoped>
.macro-wheel {
    scrollbar-width: none;
    overscroll-behavior: contain;
}
.macro-wheel::-webkit-scrollbar { display: none; }
.macro-value {
    font-size: 1rem;
    font-weight: 400;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
</style>
