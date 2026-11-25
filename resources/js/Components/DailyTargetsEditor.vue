<script setup lang="ts">
import { Check, Minus, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Card from './Card.vue';
import Button from './ui/button/Button.vue';
import { NativeSelect, NativeSelectOption } from './ui/native-select';
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
    embedded?: boolean;
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
const pickerColumns = [
    { key: 'protein', label: 'Protein' },
    { key: 'carbs', label: 'Carbs' },
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
    applySplit(customSplit.value);
}

function updateCustom(key: 'protein' | 'carbs', value: number): void {
    const other = key === 'protein' ? 'carbs' : 'protein';
    const percent = Math.min(value, 100 - customSplit.value[other]);
    customSplit.value = {...customSplit.value, [key]: percent, fat: 100 - percent - customSplit.value[other]};
    applySplit(customSplit.value);
}

function customOptions(key: 'protein' | 'carbs'): number[] {
    return percentageOptions.filter((percent) => percent <= 100 - (key === 'protein' ? customSplit.value.carbs : customSplit.value.protein));
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
        <Card :class="props.embedded && 'overflow-visible rounded-none p-0 shadow-none'">
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

            <template v-if="!props.embedded">
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
            </template>
        </Card>

        <Card class="space-y-4" :class="props.embedded && 'overflow-visible rounded-none p-0 shadow-none'">
            <div>
                <component :is="props.embedded ? 'h3' : 'h2'" class="card-title">Macro split</component>
                <p v-if="!props.embedded" class="mt-1 text-sm text-muted-foreground">How calories divide across protein, carbs, and fat.</p>
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

            <div v-if="activePreset === null" class="space-y-2 rounded-xl bg-muted p-3">
                <div class="grid grid-cols-3 gap-2">
                    <label v-for="column in pickerColumns" :key="column.key">
                        <span class="field-label">{{ column.label }}</span>
                        <NativeSelect
                            :model-value="String(customSplit[column.key])"
                            :aria-label="`${column.label} percentage`"
                            class="mt-1 w-full rounded-lg bg-card [&_select]:h-11"
                            @update:model-value="updateCustom(column.key, Number($event))"
                        >
                            <NativeSelectOption v-for="percent in customOptions(column.key)" :key="percent" :value="String(percent)">
                                {{ percent }}%
                            </NativeSelectOption>
                        </NativeSelect>
                    </label>
                    <div>
                        <span class="field-label">Fat</span>
                        <output class="mt-1 flex h-11 items-center px-2 text-base font-semibold tabular-nums text-fat">{{ customSplit.fat }}%</output>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">Fat fills the remaining percentage.</p>
            </div>

            <p v-if="!hasRepresentableSplit" class="text-sm text-destructive">This calorie target cannot be represented with 5% macro steps.</p>
            <p v-else-if="!selectedSplitWithinBounds" class="text-sm text-destructive">Adjust calories or the macro split; each macro must be 1000g or less.</p>
            <p v-for="field in ['protein_g', 'carbs_g', 'fat_g']" :key="field" v-show="props.errors?.[field]" class="text-sm text-destructive">{{ props.errors?.[field] }}</p>
        </Card>
    </div>
</template>
