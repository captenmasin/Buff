<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { gramsForSplit, hasValidFivePercentSplit, macroPresets, splitFromGrams, splitWithinGramBounds, type MacroSplit } from '../goalMacros';

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
const percentageOptions = Array.from({ length: 21 }, (_, index) => index * 5);
const customScrollerElements: Partial<Record<'protein' | 'carbs', HTMLElement>> = {};
const scrollerItemHeight = 40;

function applySplit(split: MacroSplit): void {
    const grams = gramsForSplit(Number(form.calories), split);
    form.protein_g = grams.protein;
    form.carbs_g = grams.carbs;
    form.fat_g = grams.fat;
}

function selectPreset(index: number): void {
    activePreset.value = index;
    applySplit(macroPresets[index]);
}

function selectCustom(): void {
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
        <header><p class="text-sm text-muted-foreground">Daily target</p><h1 class="text-3xl font-semibold tracking-normal text-foreground">Goals</h1></header>
        <form class="space-y-4" @submit.prevent="save">
            <Card><label><span class="text-xs font-semibold uppercase text-muted-foreground">Calories</span><Input v-model.number="form.calories" type="number" min="1" class="mt-1 text-2xl font-semibold" /></label><p v-if="form.errors.calories" class="mt-1 text-sm text-destructive">{{ form.errors.calories }}</p></Card>
            <Card class="space-y-4">
                <div><h2 class="font-semibold">Macros</h2><p class="text-sm text-muted-foreground">Protein / Carbs / Fat</p></div>
                <div class="grid grid-cols-2 gap-2"><Button v-for="(preset, index) in macroPresets" :key="index" type="button" :variant="activePreset === index ? 'default' : 'surface'" :aria-pressed="activePreset === index" :disabled="!splitWithinGramBounds(Number(form.calories), preset)" @click="selectPreset(index)">{{ preset.protein }} / {{ preset.carbs }} / {{ preset.fat }}</Button><Button type="button" :variant="activePreset === null ? 'default' : 'surface'" :aria-pressed="activePreset === null" @click="selectCustom">Custom</Button></div>
                <div v-if="activePreset === null" class="rounded-md border border-border/60 bg-muted p-3"><div class="mb-2 grid grid-cols-3 text-center text-xs font-semibold uppercase text-muted-foreground"><span>Protein</span><span>Carbs</span><span>Fat</span></div><div class="relative grid grid-cols-3 divide-x divide-border/70"><div class="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-10 -translate-y-1/2 border-y border-border bg-card/95" /><div v-for="key in ['protein', 'carbs'] as const" :key="key" :ref="(element) => setCustomScrollerRef(key, element)" class="macro-wheel h-40 snap-y snap-mandatory overflow-y-auto py-[60px] text-center" @scroll.passive="handleCustomScroller(key, $event)"><Button v-for="percent in customOptions(key)" :key="percent" type="button" variant="ghost" class="relative z-20 h-10 w-full snap-center text-xl" :class="customSplit[key] === percent ? 'text-primary' : 'text-muted-foreground/40'" @click="updateCustom(key, percent); scrollToCustom(key, percent)">{{ percent }}<span v-if="customSplit[key] === percent" class="ml-1 text-sm">%</span></Button></div><div class="grid place-items-center text-xl font-semibold text-muted-foreground">{{ customSplit.fat }}%</div></div></div>
                <div class="grid grid-cols-3 gap-2 text-center"><div><p class="text-xs font-semibold uppercase text-muted-foreground">Protein</p><p class="mt-1 font-semibold">{{ activeSplit.protein }}%</p><p class="text-sm text-muted-foreground">{{ generatedGrams.protein }}g</p></div><div><p class="text-xs font-semibold uppercase text-muted-foreground">Carbs</p><p class="mt-1 font-semibold">{{ activeSplit.carbs }}%</p><p class="text-sm text-muted-foreground">{{ generatedGrams.carbs }}g</p></div><div><p class="text-xs font-semibold uppercase text-muted-foreground">Fat</p><p class="mt-1 font-semibold">{{ activeSplit.fat }}%</p><p class="text-sm text-muted-foreground">{{ generatedGrams.fat }}g</p></div></div>
                <p class="text-center text-sm font-semibold text-muted-foreground">100% allocated</p>
                <p v-if="!hasRepresentableSplit" class="text-sm text-destructive">This calorie target cannot be represented with 5% macro steps.</p><p v-else-if="!selectedSplitWithinBounds" class="text-sm text-destructive">Adjust calories or the macro split; each macro must be 1000g or less.</p>
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
