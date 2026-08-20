<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onUnmounted, ref } from 'vue';
import { Camera, Image as ImageIcon, LoaderCircle, Pencil, Plus, Trash2, TrendingDown, TrendingUp, X } from '@lucide/vue';
import AppSheet from '../Components/AppSheet.vue';
import Card from '../Components/Card.vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Textarea from '../Components/ui/textarea/Textarea.vue';
import { formatBodyValue, heightFromCm, heightToCm, weightFromKg, weightToKg, type HeightUnit, type WeightUnit } from '../bodyUnits';
import { resizePhoto } from '../photoResize';

interface BodyMetric { id: string; date: string; weight_kg: number; body_fat_percent: number | null; notes: string | null }
interface BodyGoals { height_cm: number | null; target_weight_kg: number | null; target_body_fat_percent: number | null }
interface BodyDelta { weight_kg: number; body_fat_percent: number | null }
interface UnitPreferences { weight_unit: WeightUnit; height_unit: HeightUnit }
interface ChartRange { min: number; max: number }
interface SelectedPhoto { file: File; preview: string }
interface ProgressPhoto { id: string; url: string; mime_type?: string }

const props = withDefaults(defineProps<{
    today: string;
    preferences: UnitPreferences;
    latest?: BodyMetric | null;
    goals?: BodyGoals | null;
    delta?: BodyDelta | null;
    history: BodyMetric[];
}>(), { latest: null, goals: null, delta: null });

const openSheet = ref<'metric' | 'profile' | null>(null);
const pendingDelete = ref<BodyMetric | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const selectedPhotos = ref<SelectedPhoto[]>([]);
const photoUploadError = ref('');
const photoUploading = ref(false);
const photosMetric = ref<BodyMetric | null>(null);
const remotePhotos = ref<ProgressPhoto[]>([]);
const remotePhotosLoading = ref(false);
const photoCache = ref<Record<string, ProgressPhoto[]>>({});
let sheetTrigger: HTMLElement | null = null;
let photoRequest = 0;

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
    photoUploadError.value = '';
}

function clearSelectedPhotos(): void {
    selectedPhotos.value.forEach(({preview}) => URL.revokeObjectURL(preview));
    selectedPhotos.value = [];
}

function close(): void {
    openSheet.value = null;
    metricForm.clearErrors();
    profileForm.clearErrors();
    photoUploadError.value = '';
    clearSelectedPhotos();
    sheetTrigger?.focus();
    sheetTrigger = null;
}

async function selectProgressPhotos(event: Event): Promise<void> {
    const input = event.target instanceof HTMLInputElement ? event.target : null;
    const files = Array.from(input?.files ?? []).slice(0, 3 - selectedPhotos.value.length);

    for (const file of files) {
        const resized = await resizePhoto(file);
        selectedPhotos.value.push({file: resized, preview: URL.createObjectURL(resized)});
    }

    if (input) {
        input.value = '';
    }
}

function removeSelectedPhoto(index: number): void {
    const [photo] = selectedPhotos.value.splice(index, 1);

    if (photo) {
        URL.revokeObjectURL(photo.preview);
    }
}

async function uploadSelectedPhotos(metricId: string): Promise<boolean> {
    if (selectedPhotos.value.length === 0) {
        return true;
    }

    photoUploading.value = true;
    photoUploadError.value = '';

    const data = new FormData();
    selectedPhotos.value.forEach(({file}) => data.append('photos[]', file));

    try {
        await axios.post(`/progress/body-metrics/${metricId}/photos`, data);
        delete photoCache.value[metricId];

        return true;
    } catch (error) {
        photoUploadError.value = (axios.isAxiosError(error) ? error.response?.data?.message : null)
            || 'Could not upload progress photos. Check your connection.';

        return false;
    } finally {
        photoUploading.value = false;
    }
}

function saveMetric(): void {
    metricForm.transform((data) => ({ ...data, weight_kg: weightToKg(data.weight_kg, props.preferences.weight_unit) }))
        .post('/progress/body-metrics', {
            preserveScroll: true,
            onSuccess: async () => {
                const metric = props.history.find((entry) => entry.date === metricForm.date)
                    ?? (props.latest?.date === metricForm.date ? props.latest : null);

                if (metric && selectedPhotos.value.length > 0) {
                    const uploaded = await uploadSelectedPhotos(metric.id);

                    if (!uploaded) {
                        return;
                    }
                }

                close();
            },
        });
}

function saveProfile(): void {
    profileForm.transform((data) => ({
        ...data,
        height_cm: heightToCm(data.height_cm, props.preferences.height_unit),
        target_weight_kg: weightToKg(data.target_weight_kg, props.preferences.weight_unit),
    })).put('/progress/body-profile', { preserveScroll: true, onSuccess: close });
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
function requestDelete(metric: BodyMetric): void { pendingDelete.value = metric; }
function cancelDelete(): void { pendingDelete.value = null; }
function confirmDelete(): void {
    if (!pendingDelete.value) {
        return;
    }

    router.delete(`/progress/body-metrics/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: cancelDelete,
    });
}

async function openPhotos(metric: BodyMetric, event: Event): Promise<void> {
    sheetTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    photosMetric.value = metric;
    remotePhotos.value = photoCache.value[metric.id] ?? [];
    const request = ++photoRequest;

    if (photoCache.value[metric.id]) {
        return;
    }

    remotePhotosLoading.value = true;

    try {
        const {data} = await axios.get(`/progress/body-metrics/${metric.id}/photos`);

        if (request === photoRequest) {
            remotePhotos.value = data.photos || [];
            photoCache.value[metric.id] = remotePhotos.value;
        }
    } catch {
        if (request === photoRequest) {
            remotePhotos.value = [];
        }
    } finally {
        if (request === photoRequest) {
            remotePhotosLoading.value = false;
        }
    }
}

function closePhotos(): void {
    photoRequest++;
    photosMetric.value = null;
    remotePhotos.value = [];
    remotePhotosLoading.value = false;
    sheetTrigger?.focus();
    sheetTrigger = null;
}

onUnmounted(() => {
    clearSelectedPhotos();
});
</script>

<template>
    <Head title="Progress" />
    <section class="space-y-5">
        <PageHeader>Progress</PageHeader>
        <div v-if="hasHistory" class="grid grid-cols-2 gap-2">
            <Button size="sm" @click="open('metric', $event)"><Plus :size="17" />Log measurement</Button>
            <Button size="sm" variant="surface" @click="open('profile', $event)"><Pencil :size="17" />Edit body profile</Button>
        </div>

        <Card v-if="!hasHistory" class="space-y-4">
            <h2 class="font-semibold tracking-tight">Start tracking your progress</h2>
            <p class="text-sm text-muted-foreground">Your first measurement unlocks trends and history.</p>
            <div class="grid grid-cols-2 gap-2">
                <Button @click="open('metric', $event)"><Plus :size="18" />Log measurement</Button>
                <Button variant="surface" @click="open('profile', $event)"><Pencil :size="18" />Edit body profile</Button>
            </div>
        </Card>

        <template v-else>
            <article class="grid grid-cols-3 gap-2">
                <Card class="px-3">
                    <p class="field-label">Weight</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ formatBodyValue(latestWeight) }}<span class="text-sm font-medium text-muted-foreground"> {{ preferences.weight_unit }}</span></p>
                    <p class="mt-1 flex items-center gap-1 text-sm" :class="delta?.weight_kg > 0 ? 'text-destructive' : 'text-success-foreground'">
                        <component :is="delta?.weight_kg > 0 ? TrendingUp : TrendingDown" v-if="hasDelta" :size="15" />
                        {{ hasDelta ? deltaLabel(displayDeltaWeight, ` ${preferences.weight_unit}`) : 'First entry' }}
                    </p>
                </Card>
                <Card class="px-3">
                    <p class="field-label">Body fat</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ latest?.body_fat_percent ?? '--' }}<span v-if="latest?.body_fat_percent !== null" class="text-sm font-medium text-muted-foreground">%</span></p>
                    <p class="mt-1 text-sm">{{ hasDelta ? deltaLabel(delta?.body_fat_percent, '%') : 'First entry' }}</p>
                </Card>
                <Card class="px-3">
                    <p class="field-label">BMI</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ currentBmi ?? '--' }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ displayHeight ? `${formatBodyValue(displayHeight)} ${preferences.height_unit}` : 'Set height' }}</p>
                </Card>
            </article>
            <Card>
                <h2 class="font-semibold tracking-tight">Trends</h2>
                <div class="mt-4 grid gap-5">
                    <div>
                        <div class="mb-2 flex justify-between">
                            <span class="field-label">Weight</span>
                            <span v-if="displayTargetWeight" class="text-xs text-muted-foreground">Goal {{ formatBodyValue(displayTargetWeight) }} {{ preferences.weight_unit }}</span>
                        </div>
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full rounded-xl bg-muted">
                            <line v-if="targetY(displayTargetWeight, weightRange) !== null" x1="0" x2="100" :y1="targetY(displayTargetWeight, weightRange)" :y2="targetY(displayTargetWeight, weightRange)" stroke="var(--food)" stroke-width="1.5" stroke-dasharray="4 3" />
                            <polyline :points="weightPoints" fill="none" stroke="var(--primary)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div>
                        <div class="mb-2 flex justify-between">
                            <span class="field-label">Body fat</span>
                            <span v-if="goals?.target_body_fat_percent" class="text-xs text-muted-foreground">Goal {{ goals.target_body_fat_percent }}%</span>
                        </div>
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full rounded-xl bg-muted">
                            <line v-if="targetY(goals?.target_body_fat_percent, bodyFatRange) !== null" x1="0" x2="100" :y1="targetY(goals?.target_body_fat_percent, bodyFatRange)" :y2="targetY(goals?.target_body_fat_percent, bodyFatRange)" stroke="var(--food)" stroke-width="1.5" stroke-dasharray="4 3" />
                            <polyline :points="bodyFatPoints" fill="none" stroke="var(--fat)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
            </Card>
            <section class="space-y-3">
                <h2 class="text-lg font-semibold tracking-tight">Recent history</h2>
                <Card class="divide-y divide-border/60 py-2">
                    <div v-for="metric in history" :key="metric.id" class="flex items-start gap-3 py-3 first:pt-1 last:pb-1">
                        <div class="min-w-0 flex-1">
                            <div class="flex justify-between gap-3">
                                <p class="font-semibold">{{ metric.date }}</p>
                                <p class="text-sm text-muted-foreground">{{ formatBodyValue(weightFromKg(metric.weight_kg, preferences.weight_unit)) }} {{ preferences.weight_unit }}<span v-if="metric.body_fat_percent !== null"> · {{ metric.body_fat_percent }}%</span></p>
                            </div>
                            <p v-if="metric.notes" class="mt-1 text-sm text-muted-foreground">{{ metric.notes }}</p>
                            <div v-if="photoCache[metric.id]?.length" class="mt-2 flex gap-1.5">
                                <button
                                    v-for="photo in photoCache[metric.id].slice(0, 3)"
                                    :key="photo.id"
                                    type="button"
                                    class="h-12 w-12 overflow-hidden rounded-lg bg-muted"
                                    aria-label="View progress photos"
                                    @click="openPhotos(metric, $event)"
                                >
                                    <img :src="photo.url" alt="" class="h-full w-full object-cover">
                                </button>
                            </div>
                        </div>
                        <Button variant="ghost" size="icon" class="rounded-full" aria-label="View progress photos" @click="openPhotos(metric, $event)">
                            <ImageIcon :size="18" />
                        </Button>
                        <Button variant="ghost" size="icon" class="rounded-full" aria-label="Remove progress item" @click="requestDelete(metric)"><Trash2 :size="18" /></Button>
                    </div>
                </Card>
            </section>
        </template>

        <AppSheet :open="Boolean(openSheet)" labelled-by="progress-sheet-title" @close="close">
                <div class="mb-4 flex justify-between gap-3">
                    <h2 id="progress-sheet-title" class="text-xl font-semibold tracking-tight">{{ openSheet === 'metric' ? 'Log measurement' : 'Edit body profile' }}</h2>
                    <Button variant="ghost" size="icon" class="rounded-full" aria-label="Close" @click="close"><X :size="20" /></Button>
                </div>
                <form v-if="openSheet === 'metric'" class="space-y-3" @submit.prevent="saveMetric">
                    <label class="block">
                        <span class="field-label">Date</span>
                        <Input v-model="metricForm.date" type="date" class="mt-1" />
                        <span v-if="metricForm.errors.date" class="text-sm text-destructive">{{ metricForm.errors.date }}</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label>
                            <span class="field-label">Weight {{ preferences.weight_unit }}</span>
                            <Input v-model="metricForm.weight_kg" type="number" min="1" step="0.1" class="mt-1" />
                            <span v-if="metricForm.errors.weight_kg" class="text-sm text-destructive">{{ metricForm.errors.weight_kg }}</span>
                        </label>
                        <label>
                            <span class="field-label">Body fat %</span>
                            <Input v-model="metricForm.body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" />
                            <span v-if="metricForm.errors.body_fat_percent" class="text-sm text-destructive">{{ metricForm.errors.body_fat_percent }}</span>
                        </label>
                    </div>
                    <label class="block">
                        <span class="field-label">Notes</span>
                        <Textarea v-model="metricForm.notes" rows="3" class="mt-1" />
                    </label>
                    <div class="space-y-2">
                        <span class="field-label">Progress photos</span>
                        <div v-if="selectedPhotos.length" class="grid grid-cols-3 gap-2">
                            <div v-for="(photo, index) in selectedPhotos" :key="photo.preview" class="relative aspect-square overflow-hidden rounded-xl bg-muted">
                                <img :src="photo.preview" alt="Selected progress" class="h-full w-full object-cover">
                                <Button type="button" size="icon" variant="inverse" class="absolute right-1 top-1 h-8 w-8" aria-label="Remove photo" @click="removeSelectedPhoto(index)">
                                    <X :size="16" />
                                </Button>
                            </div>
                        </div>
                        <input
                            ref="photoInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/*"
                            capture="environment"
                            multiple
                            class="sr-only"
                            @change="selectProgressPhotos"
                        >
                        <Button
                            v-if="selectedPhotos.length < 3"
                            type="button"
                            variant="surface"
                            class="w-full"
                            @click="photoInput?.click()"
                        >
                            <Camera :size="18" />
                            {{ selectedPhotos.length ? 'Add another' : 'Take or choose photo' }}
                        </Button>
                        <p v-if="photoUploadError" class="text-sm text-destructive">{{ photoUploadError }}</p>
                    </div>
                    <Button class="w-full" :disabled="metricForm.processing || photoUploading">
                        <LoaderCircle v-if="metricForm.processing || photoUploading" :size="18" class="animate-spin" />
                        {{ photoUploading ? 'Uploading photos…' : 'Save progress' }}
                    </Button>
                </form>
                <form v-else class="space-y-3" @submit.prevent="saveProfile">
                    <div class="grid grid-cols-2 gap-3">
                        <label>
                            <span class="field-label">Height {{ preferences.height_unit }}</span>
                            <Input v-model="profileForm.height_cm" type="number" min="1" step="0.1" class="mt-1" />
                            <span v-if="profileForm.errors.height_cm" class="text-sm text-destructive">{{ profileForm.errors.height_cm }}</span>
                        </label>
                        <label>
                            <span class="field-label">Target {{ preferences.weight_unit }}</span>
                            <Input v-model="profileForm.target_weight_kg" type="number" min="1" step="0.1" class="mt-1" />
                            <span v-if="profileForm.errors.target_weight_kg" class="text-sm text-destructive">{{ profileForm.errors.target_weight_kg }}</span>
                        </label>
                    </div>
                    <label class="block">
                        <span class="field-label">Target body fat %</span>
                        <Input v-model="profileForm.target_body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" />
                        <span v-if="profileForm.errors.target_body_fat_percent" class="text-sm text-destructive">{{ profileForm.errors.target_body_fat_percent }}</span>
                    </label>
                    <Button class="w-full" :disabled="profileForm.processing">Save body profile</Button>
                </form>
        </AppSheet>
        <AppSheet :open="Boolean(photosMetric)" labelled-by="progress-photos-title" @close="closePhotos">
            <div class="mb-4 flex justify-between gap-3">
                <h2 id="progress-photos-title" class="text-xl font-semibold tracking-tight">
                    Photos · {{ photosMetric?.date }}
                </h2>
                <Button variant="ghost" size="icon" class="rounded-full" aria-label="Close" @click="closePhotos"><X :size="20" /></Button>
            </div>
            <div v-if="remotePhotosLoading" class="flex items-center gap-2 text-sm text-muted-foreground" role="status">
                <LoaderCircle :size="16" class="animate-spin" />
                Loading progress photos…
            </div>
            <div v-else-if="remotePhotos.length" class="grid grid-cols-2 gap-2">
                <img
                    v-for="photo in remotePhotos"
                    :key="photo.id"
                    :src="photo.url"
                    alt="Progress photo"
                    class="aspect-square w-full rounded-xl object-cover"
                >
            </div>
            <p v-else class="text-sm text-muted-foreground">No photos for this measurement yet.</p>
        </AppSheet>
        <ConfirmSheet
            :open="Boolean(pendingDelete)"
            title="Delete progress item"
            :message="pendingDelete ? `Delete progress item from ${pendingDelete.date}?` : ''"
            @cancel="cancelDelete"
            @confirm="confirmDelete"
        />
    </section>
</template>
