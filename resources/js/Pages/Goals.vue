<script setup lang="ts">
import type { PendingVisit } from '@inertiajs/core';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronUp, Pencil } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import DailyTargetsEditor from '../Components/DailyTargetsEditor.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { formatBodyValue, weightFromKg, weightToKg, type WeightUnit } from '../bodyUnits';
import { macroEnergyShares } from '../goalMacros';

const props = defineProps<{
    preferences: { weight_unit: WeightUnit };
    goal: {
        calories: number;
        protein_g: number;
        carbs_g: number;
        fat_g: number;
        target_weight_kg: number | null;
        target_body_fat_percent: number | null;
    };
}>();

const targetsValid = ref(true);
const nutritionEditor = ref<HTMLDetailsElement | null>(null);
const bodyEditor = ref<HTMLDetailsElement | null>(null);
const calorieFormatter = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 });
const editClass = 'absolute right-5 top-4 flex min-h-11 cursor-pointer list-none items-center gap-1.5 rounded-lg border px-2.5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden';
const form = useForm('Goals', {
    calories: props.goal.calories,
    protein_g: props.goal.protein_g,
    carbs_g: props.goal.carbs_g,
    fat_g: props.goal.fat_g,
    target_weight_kg: weightFromKg(props.goal.target_weight_kg, props.preferences.weight_unit) ?? '',
    target_body_fat_percent: props.goal.target_body_fat_percent ?? '',
});
const pendingVisit = shallowRef<PendingVisit | null>(null);
let stopWatchingVisits: (() => void) | undefined;
let discarding = false;
const targetWeightMinimum = weightFromKg(20, props.preferences.weight_unit) ?? 20;
const targetWeightMaximum = weightFromKg(1000, props.preferences.weight_unit) ?? 1000;
const targetWeightIsValid = computed(() => {
    const weight = Number(form.target_weight_kg);

    return form.target_weight_kg === '' || (Number.isFinite(weight) && weight >= targetWeightMinimum && weight <= targetWeightMaximum);
});

const macroTargets = computed(() => {
    const grams = { protein: form.protein_g, carbs: form.carbs_g, fat: form.fat_g };
    const shares = macroEnergyShares(grams);

    return [
        { label: 'Protein', grams: grams.protein, share: shares.protein, color: 'bg-protein' },
        { label: 'Carbs', grams: grams.carbs, share: shares.carbs, color: 'bg-carbs' },
        { label: 'Fat', grams: grams.fat, share: shares.fat, color: 'bg-fat' },
    ];
});

function openNutritionEditor(): void {
    if (nutritionEditor.value) {
        nutritionEditor.value.open = true;
    }
}

function openBodyEditor(): void {
    if (bodyEditor.value) {
        bodyEditor.value.open = true;
    }
}

function save(): void {
    if (!targetsValid.value) {
        openNutritionEditor();
        return;
    }

    if (!targetWeightIsValid.value) {
        openBodyEditor();
        return;
    }

    form.transform((data) => ({
        ...data,
        target_weight_kg: weightToKg(data.target_weight_kg, props.preferences.weight_unit),
    })).put('/goals', {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: (errors) => {
            if (errors.calories || errors.protein_g || errors.carbs_g || errors.fat_g) {
                openNutritionEditor();
            }

            if (errors.target_weight_kg || errors.target_body_fat_percent) {
                openBodyEditor();
            }
        },
    });
}

function warnBeforeUnload(event: BeforeUnloadEvent): void {
    event.preventDefault();
    event.returnValue = true;
}

function discardChanges(): void {
    const visit = pendingVisit.value;
    if (!visit) return;

    pendingVisit.value = null;
    form.reset();
    discarding = true;
    try {
        router.visit(visit.url, visit);
    } finally {
        discarding = false;
    }
}

onMounted(() => {
    stopWatchingVisits = router.on('before', ({detail: {visit}}) => {
        if (discarding || !form.isDirty || visit.prefetch || visit.url.pathname === '/goals') return;
        if (form.processing) return false;

        pendingVisit.value = visit;
        return false;
    });

    watch(() => form.isDirty, (dirty) => {
        if (dirty) window.addEventListener('beforeunload', warnBeforeUnload);
        else window.removeEventListener('beforeunload', warnBeforeUnload);
    }, {immediate: true});
});

onUnmounted(() => {
    stopWatchingVisits?.();
    window.removeEventListener('beforeunload', warnBeforeUnload);
});
</script>

<template>
    <Head title="Goals" />
    <section class="space-y-5">
        <PageHeader>Goals</PageHeader>
        <form class="grid items-start gap-4 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]" @submit.prevent="save">
            <section aria-labelledby="nutrition-targets-title" class="relative min-w-0 rounded-2xl bg-brand-night text-brand-white shadow-card">
                <div class="p-5">
                    <h2 id="nutrition-targets-title" class="min-h-9 pr-27 text-lg leading-7 font-semibold">Nutrition targets</h2>
                    <p class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <strong class="text-5xl leading-none font-bold tracking-tight text-brand-acid tabular-nums sm:text-6xl">
                            {{ Number.isFinite(form.calories) && form.calories > 0 ? calorieFormatter.format(form.calories) : '—' }}
                        </strong>
                        <span class="text-sm text-brand-white/75">kcal per day</span>
                    </p>

                    <div class="mt-4 flex h-2 overflow-hidden rounded-full bg-brand-white/15" aria-hidden="true">
                        <span
                            v-for="macro in macroTargets"
                            :key="macro.label"
                            :class="macro.color"
                            :style="{ width: `${macro.share}%` }"
                        />
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2">
                        <div v-for="macro in macroTargets" :key="macro.label" class="min-w-0">
                            <dt class="flex items-center gap-1.5 text-sm text-brand-white/75">
                                <span class="size-1.5 shrink-0 rounded-full" :class="macro.color" aria-hidden="true" />
                                {{ macro.label }}
                            </dt>
                            <dd class="flex flex-wrap items-baseline gap-x-1.5 tabular-nums">
                                <strong class="text-base font-semibold whitespace-nowrap">{{ Number.isFinite(macro.grams) ? `${Math.round(macro.grams)} g` : '—' }}</strong>
                                <span class="text-xs text-brand-white/75">{{ Math.round(macro.share) }}%</span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <details ref="nutritionEditor" class="group" @invalid.capture="openNutritionEditor">
                    <summary :class="[editClass, 'border-brand-white/20 text-brand-white/75 hover:border-brand-white/60']">
                        <span class="flex items-center gap-1.5 group-open:hidden">
                            <Pencil :size="14" aria-hidden="true" />Edit<span class="sr-only"> nutrition targets</span>
                        </span>
                        <span class="hidden items-center gap-1.5 group-open:flex">
                            <ChevronUp :size="14" aria-hidden="true" />Collapse<span class="sr-only"> nutrition editor</span>
                        </span>
                    </summary>
                    <DailyTargetsEditor
                        v-model:calories="form.calories"
                        v-model:protein_g="form.protein_g"
                        v-model:carbs_g="form.carbs_g"
                        v-model:fat_g="form.fat_g"
                        :errors="form.errors"
                        embedded
                        class="rounded-b-2xl bg-card p-5 text-foreground"
                        @valid="targetsValid = $event"
                    />
                </details>
            </section>

            <section aria-labelledby="body-targets-title" class="relative min-w-0 rounded-2xl bg-card shadow-card">
                <div class="p-5">
                    <h2 id="body-targets-title" class="min-h-9 pr-27 text-lg leading-7 font-semibold">Body targets</h2>
                    <dl class="mt-2 grid grid-cols-2 gap-3 lg:grid-cols-1 lg:gap-2">
                        <div class="border-t border-border pt-2 lg:flex lg:items-baseline lg:justify-between lg:gap-2">
                            <dt class="text-sm text-muted-foreground">Weight</dt>
                            <dd class="font-semibold">{{ form.target_weight_kg === '' ? 'Not set' : `${formatBodyValue(Number(form.target_weight_kg))} ${preferences.weight_unit}` }}</dd>
                        </div>
                        <div class="border-t border-border pt-2 lg:flex lg:items-baseline lg:justify-between lg:gap-2">
                            <dt class="text-sm text-muted-foreground">Body fat</dt>
                            <dd class="font-semibold">{{ form.target_body_fat_percent === '' ? 'Not set' : `${formatBodyValue(Number(form.target_body_fat_percent))}%` }}</dd>
                        </div>
                    </dl>
                </div>

                <details ref="bodyEditor" class="group" @invalid.capture="openBodyEditor">
                    <summary :class="[editClass, 'border-border text-muted-foreground hover:border-input hover:text-foreground']">
                        <span class="flex items-center gap-1.5 group-open:hidden">
                            <Pencil :size="14" aria-hidden="true" />Edit<span class="sr-only"> body targets</span>
                        </span>
                        <span class="hidden items-center gap-1.5 group-open:flex">
                            <ChevronUp :size="14" aria-hidden="true" />Collapse<span class="sr-only"> body editor</span>
                        </span>
                    </summary>
                    <div class="space-y-3 border-t border-border p-5">
                        <p class="text-sm text-muted-foreground">Optional. Shown on Progress when set.</p>
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                            <label class="min-w-0">
                                <span class="field-label">Target weight ({{ preferences.weight_unit }})</span>
                                <Input
                                    v-model.number="form.target_weight_kg"
                                    type="number"
                                    inputmode="decimal"
                                    :min="targetWeightMinimum"
                                    :max="targetWeightMaximum"
                                    step="0.1"
                                    placeholder="Not set"
                                    class="mt-1"
                                    :aria-invalid="Boolean(form.errors.target_weight_kg) || !targetWeightIsValid"
                                    :aria-describedby="form.errors.target_weight_kg || !targetWeightIsValid ? 'goal-weight-error' : undefined"
                                    @update:model-value="form.clearErrors('target_weight_kg')"
                                />
                                <span v-if="!targetWeightIsValid" id="goal-weight-error" class="text-sm text-destructive">Target weight must be between {{ targetWeightMinimum }} and {{ targetWeightMaximum }} {{ preferences.weight_unit }}.</span>
                                <span v-else-if="form.errors.target_weight_kg" id="goal-weight-error" class="text-sm text-destructive">{{ form.errors.target_weight_kg }}</span>
                            </label>
                            <label class="min-w-0">
                                <span class="field-label">Target body fat (%)</span>
                                <Input
                                    v-model.number="form.target_body_fat_percent"
                                    type="number"
                                    inputmode="decimal"
                                    min="1"
                                    max="80"
                                    step="0.1"
                                    placeholder="Not set"
                                    class="mt-1"
                                    :aria-invalid="Boolean(form.errors.target_body_fat_percent)"
                                    :aria-describedby="form.errors.target_body_fat_percent ? 'goal-body-fat-error' : undefined"
                                    @update:model-value="form.clearErrors('target_body_fat_percent')"
                                />
                                <span v-if="form.errors.target_body_fat_percent" id="goal-body-fat-error" class="text-sm text-destructive">{{ form.errors.target_body_fat_percent }}</span>
                            </label>
                        </div>
                    </div>
                </details>
            </section>

            <div class="flex justify-end lg:col-span-2">
                <Button
                    type="submit"
                    class="w-full sm:w-auto"
                    size="lg"
                    :disabled="!form.isDirty"
                    :loading="form.processing"
                    loading-label="Saving goals…"
                >
                    Save goals
                </Button>
            </div>
        </form>
        <ConfirmSheet
            :open="pendingVisit !== null"
            title="Discard goal changes?"
            message="Your edits haven’t been saved."
            cancel-label="Keep editing"
            confirm-label="Discard changes"
            @cancel="pendingVisit = null"
            @confirm="discardChanges"
        />
    </section>
</template>
