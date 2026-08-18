<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { Pencil, Plus, Trash2, TrendingDown, TrendingUp, X } from '@lucide/vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Textarea from '../Components/ui/textarea/Textarea.vue';
import { formatBodyValue, heightFromCm, heightToCm, weightFromKg, weightToKg, type HeightUnit, type WeightUnit } from '../bodyUnits';

interface BodyMetric { id: number; date: string; weight_kg: number; body_fat_percent: number | null; notes: string | null }
interface BodyGoals { height_cm: number | null; target_weight_kg: number | null; target_body_fat_percent: number | null }
interface BodyDelta { weight_kg: number; body_fat_percent: number | null }
interface UnitPreferences { weight_unit: WeightUnit; height_unit: HeightUnit }
interface ChartRange { min: number; max: number }

const props = withDefaults(defineProps<{
    today: string;
    preferences: UnitPreferences;
    latest?: BodyMetric | null;
    goals?: BodyGoals | null;
    delta?: BodyDelta | null;
    history: BodyMetric[];
}>(), { latest: null, goals: null, delta: null });

const openSheet = ref<'metric' | 'profile' | null>(null);
const sheet = ref<HTMLElement | null>(null);
let sheetTrigger: HTMLElement | null = null;
const metricForm = useForm({
    date: props.today,
    weight_kg: props.latest?.date === props.today ? weightFromKg(props.latest.weight_kg, props.preferences.weight_unit) : '',
    body_fat_percent: props.latest?.date === props.today ? props.latest.body_fat_percent : '',
    notes: props.latest?.date === props.today ? props.latest.notes : '',
});
const profileForm = useForm({
    height_cm: heightFromCm(props.goals?.height_cm, props.preferences.height_unit) ?? '',
    target_weight_kg: weightFromKg(props.goals?.target_weight_kg, props.preferences.weight_unit) ?? '',
    target_body_fat_percent: props.goals?.target_body_fat_percent ?? '',
});

const hasHistory = computed(() => props.history.length > 0);
const hasDelta = computed(() => Boolean(props.delta));
const latestWeight = computed(() => weightFromKg(props.latest?.weight_kg, props.preferences.weight_unit));
const displayDeltaWeight = computed(() => weightFromKg(props.delta?.weight_kg, props.preferences.weight_unit));
const displayHeight = computed(() => heightFromCm(props.goals?.height_cm, props.preferences.height_unit));
const displayTargetWeight = computed(() => weightFromKg(props.goals?.target_weight_kg, props.preferences.weight_unit));
const currentBmi = computed(() => props.latest?.weight_kg && props.goals?.height_cm ? (Number(props.latest.weight_kg) / (Number(props.goals.height_cm) / 100) ** 2).toFixed(1) : null);
const chartMetrics = computed(() => [...props.history].reverse());
const chartWeights = computed(() => chartMetrics.value.map((metric) => weightFromKg(metric.weight_kg, props.preferences.weight_unit)));
const weightRange = computed(() => rangeFor(chartWeights.value, displayTargetWeight.value));
const bodyFatRange = computed(() => rangeFor(chartMetrics.value.map((metric) => metric.body_fat_percent), props.goals?.target_body_fat_percent));
const weightPoints = computed(() => chartPoints(chartWeights.value, weightRange.value));
const bodyFatPoints = computed(() => chartPoints(chartMetrics.value.map((metric) => metric.body_fat_percent), bodyFatRange.value));

function open(name: 'metric' | 'profile', event: Event): void {
    sheetTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    openSheet.value = name;
    nextTick(() => sheet.value?.querySelector<HTMLElement>('button:not([disabled]), input:not([disabled])')?.focus());
}

function close(): void {
    openSheet.value = null;
    metricForm.clearErrors();
    profileForm.clearErrors();
    sheetTrigger?.focus();
    sheetTrigger = null;
}

function saveMetric(): void {
    metricForm.transform((data) => ({ ...data, weight_kg: weightToKg(data.weight_kg, props.preferences.weight_unit) }))
        .post('/progress/body-metrics', { preserveScroll: true, onSuccess: close });
}

function saveProfile(): void {
    profileForm.transform((data) => ({
        ...data,
        height_cm: heightToCm(data.height_cm, props.preferences.height_unit),
        target_weight_kg: weightToKg(data.target_weight_kg, props.preferences.weight_unit),
    })).put('/progress/body-profile', { preserveScroll: true, onSuccess: close });
}

function trapSheetFocus(event: KeyboardEvent): void {
    if (event.key === 'Escape') { close(); return; }
    if (event.key !== 'Tab' || !sheet.value) return;
    const focusable = Array.from(sheet.value.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), textarea:not([disabled])'));
    const first = focusable[0];
    const last = focusable.at(-1);
    if (!first || !last) return;
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}

function rangeFor(values: Array<number | null>, target: number | null | undefined = null): ChartRange {
    const numeric = values.map(Number).filter(Number.isFinite);
    if (target !== null && target !== undefined) numeric.push(Number(target));
    if (!numeric.length) return { min: 0, max: 1 };
    const min = Math.min(...numeric); const max = Math.max(...numeric); const padding = Math.max((max - min) * 0.15, 1);
    return { min: min - padding, max: max + padding };
}

function chartPoints(values: Array<number | null>, range: ChartRange): string {
    return values.map((value, index) => value === null ? null : `${(index / Math.max(values.length - 1, 1)) * 100},${Math.max(0, Math.min(100, 100 - ((Number(value) - range.min) / (range.max - range.min)) * 100))}`).filter(Boolean).join(' ');
}

function targetY(target: number | null | undefined, range: ChartRange): number | null { return target === null || target === undefined ? null : 100 - ((Number(target) - range.min) / (range.max - range.min)) * 100; }
function deltaLabel(value: number | null | undefined, suffix: string): string { return value === null || value === undefined ? 'No change' : `${value > 0 ? '+' : ''}${value}${suffix}`; }
function removeMetric(metric: BodyMetric): void { if (window.confirm(`Delete progress item from ${metric.date}?`)) router.delete(`/progress/body-metrics/${metric.id}`, { preserveScroll: true }); }
</script>

<template>
    <Head title="Progress" />
    <section class="space-y-5">
        <header class="flex items-start justify-between gap-3">
            <div><p class="text-sm text-muted-foreground">Body metrics</p><h1 class="text-3xl font-semibold tracking-normal text-foreground">Progress</h1></div>
            <div v-if="hasHistory" class="flex gap-2"><Button size="sm" @click="open('metric', $event)"><Plus :size="17" />Log measurement</Button><Button size="sm" variant="surface" @click="open('profile', $event)"><Pencil :size="17" />Edit body profile</Button></div>
        </header>

        <Card v-if="!hasHistory" class="space-y-4"><h2 class="font-semibold">Start tracking your progress</h2><p class="text-sm text-muted-foreground">Your first measurement unlocks trends and history.</p><div class="grid grid-cols-2 gap-2"><Button @click="open('metric', $event)"><Plus :size="18" />Log measurement</Button><Button variant="surface" @click="open('profile', $event)"><Pencil :size="18" />Edit body profile</Button></div></Card>

        <template v-else>
            <article class="grid grid-cols-3 gap-3"><Card><p class="text-xs font-semibold uppercase text-muted-foreground">Weight</p><p class="mt-2 text-3xl font-semibold">{{ formatBodyValue(latestWeight) }}<span class="text-sm text-muted-foreground"> {{ preferences.weight_unit }}</span></p><p class="mt-1 text-sm" :class="delta?.weight_kg > 0 ? 'text-destructive' : 'text-success-foreground'"><component :is="delta?.weight_kg > 0 ? TrendingUp : TrendingDown" v-if="hasDelta" :size="15" />{{ hasDelta ? deltaLabel(displayDeltaWeight, ` ${preferences.weight_unit}`) : 'First entry' }}</p></Card><Card><p class="text-xs font-semibold uppercase text-muted-foreground">Body fat</p><p class="mt-2 text-3xl font-semibold">{{ latest?.body_fat_percent ?? '--' }}<span v-if="latest?.body_fat_percent !== null" class="text-sm text-muted-foreground">%</span></p><p class="mt-1 text-sm">{{ hasDelta ? deltaLabel(delta?.body_fat_percent, '%') : 'First entry' }}</p></Card><Card><p class="text-xs font-semibold uppercase text-muted-foreground">BMI</p><p class="mt-2 text-3xl font-semibold">{{ currentBmi ?? '--' }}</p><p class="mt-1 text-sm text-muted-foreground">{{ displayHeight ? `${formatBodyValue(displayHeight)} ${preferences.height_unit}` : 'Set height' }}</p></Card></article>
            <Card><h2 class="font-semibold">Trends</h2><div class="mt-4 grid gap-5"><div><div class="mb-2 flex justify-between text-xs font-semibold uppercase text-muted-foreground"><span>Weight</span><span v-if="displayTargetWeight">Goal {{ formatBodyValue(displayTargetWeight) }} {{ preferences.weight_unit }}</span></div><svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full rounded-md bg-muted"><line v-if="targetY(displayTargetWeight, weightRange) !== null" x1="0" x2="100" :y1="targetY(displayTargetWeight, weightRange)" :y2="targetY(displayTargetWeight, weightRange)" stroke="var(--food)" stroke-width="1.5" stroke-dasharray="4 3" /><polyline :points="weightPoints" fill="none" stroke="var(--primary)" stroke-width="3" /></svg></div><div><div class="mb-2 flex justify-between text-xs font-semibold uppercase text-muted-foreground"><span>Body fat</span><span v-if="goals?.target_body_fat_percent">Goal {{ goals.target_body_fat_percent }}%</span></div><svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full rounded-md bg-muted"><line v-if="targetY(goals?.target_body_fat_percent, bodyFatRange) !== null" x1="0" x2="100" :y1="targetY(goals?.target_body_fat_percent, bodyFatRange)" :y2="targetY(goals?.target_body_fat_percent, bodyFatRange)" stroke="var(--food)" stroke-width="1.5" stroke-dasharray="4 3" /><polyline :points="bodyFatPoints" fill="none" stroke="var(--fat)" stroke-width="3" /></svg></div></div></Card>
            <section class="space-y-3"><h2 class="text-lg font-semibold">Recent history</h2><Card class="divide-y divide-border/70"><div v-for="metric in history" :key="metric.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0"><div class="min-w-0 flex-1"><div class="flex justify-between gap-3"><p class="font-semibold">{{ metric.date }}</p><p class="text-sm text-muted-foreground">{{ formatBodyValue(weightFromKg(metric.weight_kg, preferences.weight_unit)) }} {{ preferences.weight_unit }}<span v-if="metric.body_fat_percent !== null"> · {{ metric.body_fat_percent }}%</span></p></div><p v-if="metric.notes" class="mt-1 text-sm text-muted-foreground">{{ metric.notes }}</p></div><Button variant="ghost" size="icon" aria-label="Remove progress item" @click="removeMetric(metric)"><Trash2 :size="18" /></Button></div></Card></section>
        </template>

        <div v-if="openSheet" class="fixed inset-0 z-50 grid place-items-end bg-foreground/30 px-4 pb-4 sm:place-items-center sm:py-4" @click.self="close" @keydown="trapSheetFocus"><Card ref="sheet" role="dialog" aria-modal="true" :aria-labelledby="`${openSheet}-sheet-title`" class="w-full max-w-md sm:max-w-lg"><div class="mb-4 flex justify-between gap-3"><h2 :id="`${openSheet}-sheet-title`" class="text-xl font-semibold">{{ openSheet === 'metric' ? 'Log measurement' : 'Edit body profile' }}</h2><Button variant="ghost" size="icon" aria-label="Close" @click="close"><X :size="20" /></Button></div><form v-if="openSheet === 'metric'" class="space-y-3" @submit.prevent="saveMetric"><label class="block"><span class="text-xs font-semibold uppercase text-muted-foreground">Date</span><Input v-model="metricForm.date" type="date" class="mt-1" /><span v-if="metricForm.errors.date" class="text-sm text-destructive">{{ metricForm.errors.date }}</span></label><div class="grid grid-cols-2 gap-3"><label><span class="text-xs font-semibold uppercase text-muted-foreground">Weight {{ preferences.weight_unit }}</span><Input v-model="metricForm.weight_kg" type="number" min="1" step="0.1" class="mt-1" /><span v-if="metricForm.errors.weight_kg" class="text-sm text-destructive">{{ metricForm.errors.weight_kg }}</span></label><label><span class="text-xs font-semibold uppercase text-muted-foreground">Body fat %</span><Input v-model="metricForm.body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" /><span v-if="metricForm.errors.body_fat_percent" class="text-sm text-destructive">{{ metricForm.errors.body_fat_percent }}</span></label></div><label class="block"><span class="text-xs font-semibold uppercase text-muted-foreground">Notes</span><Textarea v-model="metricForm.notes" rows="3" class="mt-1" /></label><Button class="w-full" :disabled="metricForm.processing">Save progress</Button></form><form v-else class="space-y-3" @submit.prevent="saveProfile"><div class="grid grid-cols-2 gap-3"><label><span class="text-xs font-semibold uppercase text-muted-foreground">Height {{ preferences.height_unit }}</span><Input v-model="profileForm.height_cm" type="number" min="1" step="0.1" class="mt-1" /><span v-if="profileForm.errors.height_cm" class="text-sm text-destructive">{{ profileForm.errors.height_cm }}</span></label><label><span class="text-xs font-semibold uppercase text-muted-foreground">Target {{ preferences.weight_unit }}</span><Input v-model="profileForm.target_weight_kg" type="number" min="1" step="0.1" class="mt-1" /><span v-if="profileForm.errors.target_weight_kg" class="text-sm text-destructive">{{ profileForm.errors.target_weight_kg }}</span></label></div><label class="block"><span class="text-xs font-semibold uppercase text-muted-foreground">Target body fat %</span><Input v-model="profileForm.target_body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" /><span v-if="profileForm.errors.target_body_fat_percent" class="text-sm text-destructive">{{ profileForm.errors.target_body_fat_percent }}</span></label><Button class="w-full" :disabled="profileForm.processing">Save body profile</Button></form></Card></div>
    </section>
</template>
