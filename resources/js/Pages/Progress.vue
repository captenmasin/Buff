<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Camera, Check, Circle, Image as ImageIcon, LoaderCircle, SwitchCamera, Trash2, TrendingDown, TrendingUp, X } from '@lucide/vue';
import AppSheet from '../Components/AppSheet.vue';
import Card from '../Components/Card.vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import PageHeader from '../Components/PageHeader.vue';
import ProgressTrendChart from '../Components/ProgressTrendChart.vue';
import Button from '../Components/ui/button/Button.vue';
import type { ChartConfig } from '../Components/ui/chart';
import Input from '../Components/ui/input/Input.vue';
import Textarea from '../Components/ui/textarea/Textarea.vue';
import {
    formatBodyValue,
    heightFromCm,
    measurementFromCm,
    measurementToCm,
    weightFromKg,
    weightToKg,
    type HeightUnit,
    type MeasurementUnit,
    type WeightUnit,
} from '../bodyUnits';
import { hapticImpact } from '../haptics';
import { photoDataUrl } from '../photoDataUrl';
import { resizePhoto } from '../photoResize';
import { buildBodyFatChartData, buildWeightChartData, chartSummary, chartXDomain, deltaTone } from '../progressChart';
import {
    isProgressPhotoPose,
    progressPhotoCaptureLabels,
    progressPhotoLabels,
    progressPhotoPoses,
    selectPoseOverlays,
    sortProgressPhotos,
    type ProgressPhotoPose,
} from '../progressPhotos';

interface BodyMetric {
    id: string;
    date: string;
    weight_kg: number;
    body_fat_percent: number | null;
    chest_cm: number | null;
    waist_cm: number | null;
    hips_cm: number | null;
    upper_arm_cm: number | null;
    thigh_cm: number | null;
    notes: string | null;
    trend_kg: number | null;
}
interface BodyProfile {
    height_cm: number | null;
    age: number | null;
    sex: string | null;
    activity_level: string | null;
}
interface BodyGoals {
    target_weight_kg: number | null;
    target_body_fat_percent: number | null;
}
interface EnergyEstimate { bmr: number; tdee: number }
interface BodyDelta { weight_kg: number; body_fat_percent: number | null }
interface WeightTrend { weight_kg: number; delta_kg: number | null }
interface UnitPreferences { weight_unit: WeightUnit; height_unit: HeightUnit; measurement_unit: MeasurementUnit }
interface MeasurementSummaryItem { value_cm: number; delta_cm: number | null }
interface SelectedPhoto { file: File; preview: string; pose: ProgressPhotoPose }
interface ProgressPhoto { id: string; url: string; mime_type?: string; pose?: string | null; pending?: boolean }

const measurementFields = [
    { key: 'chest_cm', label: 'Chest' },
    { key: 'waist_cm', label: 'Waist' },
    { key: 'hips_cm', label: 'Hips' },
    { key: 'upper_arm_cm', label: 'Upper arm' },
    { key: 'thigh_cm', label: 'Thigh' },
] as const;
type MeasurementKey = typeof measurementFields[number]['key'];
type MeasurementSummary = Record<MeasurementKey, MeasurementSummaryItem | null>;

const props = withDefaults(defineProps<{
    today: string;
    range: string;
    range_start: string;
    range_end: string;
    preferences: UnitPreferences;
    measurements: MeasurementSummary;
    latest?: BodyMetric | null;
    profile: BodyProfile;
    goals?: BodyGoals | null;
    energy?: EnergyEstimate | null;
    trend?: WeightTrend | null;
    delta?: BodyDelta | null;
    history: BodyMetric[];
}>(), { latest: null, goals: null, energy: null, trend: null, delta: null });

const pendingDelete = ref<BodyMetric | null>(null);
const pendingPhotoDelete = ref<ProgressPhoto | null>(null);
const deleteProcessing = ref(false);
const deleteError = ref('');
const chartCarousel = ref<HTMLElement | null>(null);
const activeChart = ref(0);
const photoInput = ref<HTMLInputElement | null>(null);
const sheetPhotoInput = ref<HTMLInputElement | null>(null);
const selectedPhotos = ref<Partial<Record<ProgressPhotoPose, SelectedPhoto>>>({});
const photoUploadError = ref('');
const photoUploading = ref(false);
const photosMetric = ref<BodyMetric | null>(null);
const remotePhotos = ref<ProgressPhoto[]>([]);
const remotePhotosLoading = ref(false);
const photoCache = ref<Record<string, ProgressPhoto[]>>({});
const overlayByPose = ref<Partial<Record<ProgressPhotoPose, { photo: ProgressPhoto; date: string }>>>({});
const overlayOpacity = ref(0.35);
const capturingPose = ref<ProgressPhotoPose>('front');
const libraryPose = ref<ProgressPhotoPose>('front');
const cameraOpen = ref(false);
const cameraStarting = ref(false);
const cameraReady = ref(false);
const cameraError = ref('');
const cameraFacing = ref<'user' | 'environment'>('user');
const cameraVideo = ref<HTMLVideoElement | null>(null);
const photoTargetMetric = ref<BodyMetric | null>(null);
let cameraStream: MediaStream | null = null;
let photoRequest = 0;
let overlayRequest = 0;
let photoTrigger: HTMLElement | null = null;
const photoLoads: Record<string, Promise<ProgressPhoto[]>> = {};

const metricForm = useForm({
    date: props.today,
    weight_kg: props.latest?.date === props.today ? weightFromKg(props.latest.weight_kg, props.preferences.weight_unit) : '',
    body_fat_percent: props.latest?.date === props.today ? props.latest.body_fat_percent : '',
    chest_cm: props.latest?.date === props.today ? measurementFromCm(props.latest.chest_cm, props.preferences.measurement_unit) ?? '' : '',
    waist_cm: props.latest?.date === props.today ? measurementFromCm(props.latest.waist_cm, props.preferences.measurement_unit) ?? '' : '',
    hips_cm: props.latest?.date === props.today ? measurementFromCm(props.latest.hips_cm, props.preferences.measurement_unit) ?? '' : '',
    upper_arm_cm: props.latest?.date === props.today ? measurementFromCm(props.latest.upper_arm_cm, props.preferences.measurement_unit) ?? '' : '',
    thigh_cm: props.latest?.date === props.today ? measurementFromCm(props.latest.thigh_cm, props.preferences.measurement_unit) ?? '' : '',
    notes: props.latest?.date === props.today ? props.latest.notes : '',
});

const hasHistory = computed(() => props.history.length > 0);
const hasDelta = computed(() => Boolean(props.delta));
const hasTrendDelta = computed(() => props.trend?.delta_kg !== null && props.trend?.delta_kg !== undefined);
const rangeOptions = [
    { key: '30', label: '30', accessibleLabel: '30 days' },
    { key: '90', label: '90', accessibleLabel: '90 days' },
    { key: '180', label: '180', accessibleLabel: '180 days' },
    { key: 'all', label: 'All', accessibleLabel: 'All time' },
] as const;
const latestWeight = computed(() => weightFromKg(props.trend?.weight_kg ?? props.latest?.weight_kg, props.preferences.weight_unit));
const previousWeight = computed(() => {
    const weight = weightFromKg(props.latest?.weight_kg, props.preferences.weight_unit);

    return weight === null ? undefined : String(weight);
});
const previousBodyFat = computed(() => {
    if (props.latest?.body_fat_percent != null) {
        return String(props.latest.body_fat_percent);
    }

    const prior = props.history.find((metric) => metric.body_fat_percent !== null);

    return prior?.body_fat_percent == null ? undefined : String(prior.body_fat_percent);
});
const displayTrendDelta = computed(() => weightFromKg(props.trend?.delta_kg, props.preferences.weight_unit));
const displayHeight = computed(() => heightFromCm(props.profile.height_cm, props.preferences.height_unit));
const displayTargetWeight = computed(() => weightFromKg(props.goals?.target_weight_kg, props.preferences.weight_unit));
const currentBmi = computed(() => props.latest?.weight_kg && props.profile.height_cm ? (Number(props.latest.weight_kg) / (Number(props.profile.height_cm) / 100) ** 2).toFixed(1) : null);
const chartDomain = computed(() => chartXDomain(props.range_start, props.range_end));
const weightChartData = computed(() => buildWeightChartData(
    props.history.map((metric) => ({
        date: metric.date,
        weight: weightFromKg(metric.weight_kg, props.preferences.weight_unit) ?? 0,
    })),
    props.range_start,
    props.range_end,
    displayTargetWeight.value,
));
const bodyFatChartData = computed(() => buildBodyFatChartData(
    props.history.map((metric) => ({
        date: metric.date,
        bodyFat: metric.body_fat_percent,
    })),
    props.range_start,
    props.range_end,
    props.goals?.target_body_fat_percent ?? null,
));
const hasBodyFatChart = computed(() => bodyFatChartData.value.some((row) => row.bodyFat !== undefined));
const hasMeasurements = computed(() => measurementFields.some(({ key }) => props.measurements[key] !== null));
const weightChartLines = computed(() => displayTargetWeight.value === null ? ['weight'] : ['weight', 'goal']);
const bodyFatChartLines = computed(() => props.goals?.target_body_fat_percent == null ? ['bodyFat'] : ['bodyFat', 'goal']);
const weightChartSummary = computed(() => chartSummary(
    weightChartData.value,
    'weight',
    ` ${props.preferences.weight_unit}`,
    displayTargetWeight.value,
));
const bodyFatChartSummary = computed(() => chartSummary(
    bodyFatChartData.value,
    'bodyFat',
    '%',
    props.goals?.target_body_fat_percent ?? null,
));
const weightDeltaClass = computed(() => deltaTone(
    props.trend?.delta_kg,
    props.latest?.weight_kg,
    props.goals?.target_weight_kg,
));
const bodyFatDeltaClass = computed(() => deltaTone(
    props.delta?.body_fat_percent,
    props.latest?.body_fat_percent,
    props.goals?.target_body_fat_percent,
));
const weightChartConfig: ChartConfig = {
    weight: { label: 'Weight', color: 'var(--chart-1)' },
    goal: { label: 'Goal', color: 'var(--food)' },
};
const bodyFatChartConfig: ChartConfig = {
    bodyFat: { label: 'Body fat', color: 'var(--fat)' },
    goal: { label: 'Goal', color: 'var(--food)' },
};
const selectedPhotoCount = computed(() => progressPhotoPoses.filter((pose) => Boolean(selectedPhotos.value[pose])).length);
const activeOverlay = computed(() => overlayByPose.value[capturingPose.value] ?? null);
const activeOverlayPhoto = computed(() => activeOverlay.value?.photo ?? null);
const overlaySourceDate = computed(() => activeOverlay.value?.date ?? null);
const mirrorPreview = computed(() => cameraFacing.value === 'user');

function measurementDisplay(value: number | null | undefined): number | null {
    return measurementFromCm(value, props.preferences.measurement_unit);
}

function measurementFormPayload(data: Record<MeasurementKey, number | string>): Partial<Record<MeasurementKey, number | string>> {
    return Object.fromEntries(measurementFields
        .filter(({ key }) => data[key] !== '')
        .map(({ key }) => [key, measurementToCm(data[key], props.preferences.measurement_unit)])) as Partial<Record<MeasurementKey, number | string>>;
}

function photoPoseLabel(pose: string | null | undefined): string {
    return isProgressPhotoPose(pose) ? progressPhotoLabels[pose] : 'Progress photo';
}

function cachePhotos(metricId: string, photos: ProgressPhoto[]): ProgressPhoto[] {
    const sorted = sortProgressPhotos(photos);
    photoCache.value[metricId] = sorted;

    return sorted;
}

function cachedPhotos(metricId: string): ProgressPhoto[] | undefined {
    return Object.prototype.hasOwnProperty.call(photoCache.value, metricId)
        ? photoCache.value[metricId]
        : undefined;
}

function loadPhotosForMetric(metricId: string): Promise<ProgressPhoto[]> {
    const cached = cachedPhotos(metricId);

    if (cached !== undefined) {
        return Promise.resolve(cached);
    }

    const inflight = photoLoads[metricId] ?? axios.get(`/progress/body-metrics/${metricId}/photos`)
        .then(({data}) => cachePhotos(metricId, data.photos || []))
        .catch(() => cachePhotos(metricId, []))
        .finally(() => {
            delete photoLoads[metricId];
        });

    photoLoads[metricId] = inflight;

    return inflight;
}

async function loadHistoryPhotos(): Promise<void> {
    await Promise.all(props.history.map((metric) => loadPhotosForMetric(metric.id)));
}

function clearSelectedPhotos(): void {
    progressPhotoPoses.forEach((pose) => {
        const photo = selectedPhotos.value[pose];

        if (photo) {
            URL.revokeObjectURL(photo.preview);
        }
    });
    selectedPhotos.value = {};
}

async function assignPhoto(pose: ProgressPhotoPose, file: File): Promise<void> {
    const existing = selectedPhotos.value[pose];

    if (existing) {
        URL.revokeObjectURL(existing.preview);
    }

    const resized = await resizePhoto(file);
    selectedPhotos.value[pose] = {file: resized, preview: URL.createObjectURL(resized), pose};
}

function nextEmptyPose(after: ProgressPhotoPose): ProgressPhotoPose | null {
    const start = progressPhotoPoses.indexOf(after) + 1;

    for (let offset = 0; offset < progressPhotoPoses.length; offset++) {
        const pose = progressPhotoPoses[(start + offset) % progressPhotoPoses.length];

        if (!selectedPhotos.value[pose]) {
            return pose;
        }
    }

    return null;
}

async function loadOverlayPhotos(): Promise<void> {
    const request = ++overlayRequest;
    await loadHistoryPhotos();

    if (request !== overlayRequest) {
        return;
    }

    overlayByPose.value = selectPoseOverlays(
        props.history.map((metric) => ({
            date: metric.date,
            photos: cachedPhotos(metric.id) ?? [],
        })),
        photoTargetMetric.value?.date ?? metricForm.date,
    );
}

async function selectProgressPhotos(event: Event): Promise<void> {
    const input = event.target instanceof HTMLInputElement ? event.target : null;
    const file = input?.files?.[0];

    if (file) {
        await assignPhoto(libraryPose.value, file);
    }

    if (input) {
        input.value = '';
    }

    if (file && photoTargetMetric.value) {
        await uploadTargetedPhotos();
    }
}

function removeSelectedPhoto(pose: ProgressPhotoPose): void {
    const photo = selectedPhotos.value[pose];

    if (!photo) {
        return;
    }

    URL.revokeObjectURL(photo.preview);
    const next = { ...selectedPhotos.value };
    delete next[pose];
    selectedPhotos.value = next;
}

function openLibrary(pose: ProgressPhotoPose): void {
    libraryPose.value = pose;
    stopCamera();
    photoInput.value?.click();
}

function cameraErrorMessage(error: unknown): string {
    const errorName = error instanceof DOMException || error instanceof Error ? error.name : null;

    if (errorName === 'NotAllowedError' || errorName === 'PermissionDeniedError') {
        return 'Camera permission was denied. Allow camera access for Buff, or choose a photo from your library.';
    }

    if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
        return 'No usable camera was found. Choose a photo from your library instead.';
    }

    return 'Could not open the camera. Choose a photo from your library instead.';
}

async function acquireCameraStream(): Promise<MediaStream | null> {
    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = 'Camera is not available on this device. Choose a photo from your library instead.';
        return null;
    }

    try {
        return await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: cameraFacing.value },
            },
        });
    } catch (error) {
        cameraError.value = cameraErrorMessage(error);
        return null;
    }
}

async function attachCameraStream(stream: MediaStream): Promise<void> {
    cameraStream = stream;
    await nextTick();

    if (cameraVideo.value) {
        cameraVideo.value.srcObject = stream;
        await cameraVideo.value.play().catch(() => undefined);
    }
}

function openFormCamera(pose: ProgressPhotoPose): void {
    photoTargetMetric.value = null;
    void startCamera(pose);
}

async function startCamera(pose: ProgressPhotoPose = capturingPose.value): Promise<void> {
    capturingPose.value = pose;
    stopCameraStream();
    cameraError.value = '';
    cameraStarting.value = true;
    cameraOpen.value = true;
    cameraReady.value = false;
    void loadOverlayPhotos();

    const stream = await acquireCameraStream();

    if (!stream) {
        cameraOpen.value = false;
        cameraStarting.value = false;
        return;
    }

    try {
        await attachCameraStream(stream);
    } finally {
        cameraStarting.value = false;
    }
}

function stopCameraStream(): void {
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;

    if (cameraVideo.value) {
        cameraVideo.value.srcObject = null;
    }
}

function stopCamera(): void {
    stopCameraStream();
    cameraOpen.value = false;
    cameraReady.value = false;
    cameraStarting.value = false;
}

function markCameraReady(): void {
    cameraReady.value = true;
}

async function flipCamera(): Promise<void> {
    cameraFacing.value = cameraFacing.value === 'user' ? 'environment' : 'user';
    await startCamera();
}

async function captureProgressPhoto(): Promise<void> {
    const video = cameraVideo.value;

    if (!video || !cameraReady.value) {
        return;
    }

    hapticImpact();
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, video.videoWidth);
    canvas.height = Math.max(1, video.videoHeight);
    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    if (mirrorPreview.value) {
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
    }

    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));

    if (!blob) {
        return;
    }

    const file = new File([blob], `${capturingPose.value}-${Date.now()}.jpg`, {type: 'image/jpeg'});
    await assignPhoto(capturingPose.value, file);

    const next = nextEmptyPose(capturingPose.value);

    if (next) {
        capturingPose.value = next;
    } else {
        await closeCamera();
    }
}

async function uploadSelectedPhotos(metricId: string): Promise<boolean> {
    if (selectedPhotoCount.value === 0) {
        return true;
    }

    photoUploading.value = true;
    photoUploadError.value = '';

    try {
        const photos: string[] = [];
        const poses: ProgressPhotoPose[] = [];

        for (const pose of progressPhotoPoses) {
            const photo = selectedPhotos.value[pose];

            if (photo) {
                photos.push(await photoDataUrl(photo.file));
                poses.push(pose);
            }
        }

        await axios.post(`/progress/body-metrics/${metricId}/photos`, {photos, poses});
        delete photoCache.value[metricId];
        await loadPhotosForMetric(metricId);

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
    metricForm.transform((data) => {
        const { chest_cm, waist_cm, hips_cm, upper_arm_cm, thigh_cm, ...progress } = data;

        return {
            ...progress,
            weight_kg: weightToKg(data.weight_kg, props.preferences.weight_unit),
            ...measurementFormPayload({ chest_cm, waist_cm, hips_cm, upper_arm_cm, thigh_cm }),
        };
    })
        .post(`/progress/body-metrics?range=${encodeURIComponent(props.range)}`, {
            preserveScroll: true,
            onSuccess: async (page) => {
                const history = (page.props.history as BodyMetric[] | undefined) ?? props.history;
                const latest = (page.props.latest as BodyMetric | null | undefined) ?? props.latest ?? null;
                const metric = history.find((entry) => entry.date === metricForm.date)
                    ?? (latest?.date === metricForm.date ? latest : null);

                if (metric && selectedPhotoCount.value > 0) {
                    const uploaded = await uploadSelectedPhotos(metric.id);

                    if (!uploaded) {
                        return;
                    }
                }

                clearSelectedPhotos();
                photoUploadError.value = '';
                void loadOverlayPhotos();
            },
        });
}

function visitRange(range: string): void {
    router.visit(`/progress?range=${range}`, { preserveScroll: true });
}

function showChart(index: number): void {
    activeChart.value = index;
    const carousel = chartCarousel.value;
    const firstSlide = carousel?.firstElementChild;
    const slide = carousel?.children[index];

    if (carousel && firstSlide instanceof HTMLElement && slide instanceof HTMLElement) {
        carousel.scrollTo({ left: slide.offsetLeft - firstSlide.offsetLeft });
    }
}

function syncChartSlide(event: Event): void {
    const carousel = event.currentTarget as HTMLElement;

    activeChart.value = Math.round(carousel.scrollLeft / carousel.clientWidth);
}

function deltaLabel(value: number | null | undefined, suffix: string): string { return value === null || value === undefined ? 'No change' : `${value > 0 ? '+' : ''}${value}${suffix}`; }
function requestDelete(metric: BodyMetric): void {
    deleteError.value = '';
    pendingDelete.value = metric;
}
function cancelDelete(): void {
    if (deleteProcessing.value) {
        return;
    }

    deleteError.value = '';
    pendingDelete.value = null;
}
function confirmDelete(): void {
    const metric = pendingDelete.value;

    if (!metric || deleteProcessing.value) {
        return;
    }

    deleteProcessing.value = true;
    deleteError.value = '';

    router.delete(`/progress/body-metrics/${metric.id}?range=${encodeURIComponent(props.range)}`, {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
        onError: () => {
            deleteError.value = 'Couldn’t delete this progress item. Try again.';
        },
        onFinish: () => {
            deleteProcessing.value = false;

            if (pendingDelete.value && !deleteError.value) {
                deleteError.value = 'Couldn’t delete this progress item. Try again.';
            }
        },
    });
}

function requestPhotoDelete(photo: ProgressPhoto): void {
    deleteError.value = '';
    pendingPhotoDelete.value = photo;
}
function cancelPhotoDelete(): void {
    if (deleteProcessing.value) {
        return;
    }

    deleteError.value = '';
    pendingPhotoDelete.value = null;
}
async function confirmPhotoDelete(): Promise<void> {
    const metric = photosMetric.value;
    const photo = pendingPhotoDelete.value;

    if (!metric || !photo || photo.pending || deleteProcessing.value) {
        return;
    }

    deleteProcessing.value = true;
    deleteError.value = '';
    photoUploadError.value = '';

    try {
        await axios.delete(`/progress/body-metrics/${metric.id}/photos/${encodeURIComponent(photo.id)}`);
        remotePhotos.value = remotePhotos.value.filter((entry) => entry.id !== photo.id);
        photoCache.value[metric.id] = remotePhotos.value;
        pendingPhotoDelete.value = null;
        void loadOverlayPhotos();
    } catch (error) {
        deleteError.value = (axios.isAxiosError(error) ? error.response?.data?.message : null)
            || 'Could not delete the progress photo. Check your connection.';
    } finally {
        deleteProcessing.value = false;
    }
}

function openPhotos(metric: BodyMetric, event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    hapticImpact();
    photoTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;

    window.setTimeout(() => {
        void showPhotos(metric);
    }, 0);
}

async function showPhotos(metric: BodyMetric): Promise<void> {
    photosMetric.value = metric;
    const cached = cachedPhotos(metric.id);
    remotePhotos.value = cached ?? [];
    const request = ++photoRequest;
    remotePhotosLoading.value = cached === undefined;

    try {
        const photos = await loadPhotosForMetric(metric.id);

        if (request === photoRequest) {
            remotePhotos.value = photos;
        }
    } finally {
        if (request === photoRequest) {
            remotePhotosLoading.value = false;
        }
    }
}

async function addPhotosForMetric(): Promise<void> {
    const metric = photosMetric.value;

    if (!metric) {
        return;
    }

    photoTargetMetric.value = metric;
    hapticImpact();
    capturingPose.value = 'front';
    stopCameraStream();
    cameraError.value = '';
    cameraStarting.value = true;
    cameraReady.value = false;

    const stream = await acquireCameraStream();

    if (!stream) {
        cameraStarting.value = false;
        return;
    }

    closePhotos();
    cameraOpen.value = true;
    void loadOverlayPhotos();

    try {
        await attachCameraStream(stream);
    } finally {
        cameraStarting.value = false;
    }
}

function addPhotosFromLibrary(): void {
    const metric = photosMetric.value;

    if (!metric) {
        return;
    }

    photoTargetMetric.value = metric;
    libraryPose.value = progressPhotoPoses.find((pose) => !selectedPhotos.value[pose]) ?? 'front';
    hapticImpact();
    sheetPhotoInput.value?.click();
}

async function closeCamera(): Promise<void> {
    const metric = photoTargetMetric.value;
    stopCamera();
    await uploadTargetedPhotos();

    if (metric && photosMetric.value === null) {
        await showPhotos(metric);
    }
}

function handleNativeAndroidBack(event: Event): void {
    if (!cameraOpen.value) {
        return;
    }

    event.preventDefault();
    void closeCamera();
}

async function uploadTargetedPhotos(): Promise<void> {
    const metric = photoTargetMetric.value;

    if (!metric || selectedPhotoCount.value === 0) {
        return;
    }

    const uploaded = await uploadSelectedPhotos(metric.id);

    if (!uploaded) {
        return;
    }

    photoTargetMetric.value = null;
    clearSelectedPhotos();
    await showPhotos(metric);
}

function closePhotos(): void {
    photoRequest++;
    photosMetric.value = null;
    remotePhotos.value = [];
    remotePhotosLoading.value = false;

    if (!cameraStarting.value && !cameraOpen.value) {
        photoTrigger?.focus();
    }

    photoTrigger = null;
}

watch(() => metricForm.date, () => {
    void loadOverlayPhotos();
});

watch(() => props.history.map((metric) => metric.id).join(','), () => {
    void loadHistoryPhotos().then(() => {
        void loadOverlayPhotos();
    });
}, { immediate: true });

onMounted(() => {
    window.addEventListener('buff:android-back', handleNativeAndroidBack);
});

onUnmounted(() => {
    window.removeEventListener('buff:android-back', handleNativeAndroidBack);
    overlayRequest++;
    stopCamera();
    clearSelectedPhotos();
});
</script>

<template>
    <Head title="Progress" />
    <section class="space-y-5">
        <PageHeader>
            Progress
            <template #actions>
                <Button :as="Link" href="/goals" variant="outline" size="sm">Edit goals</Button>
            </template>
        </PageHeader>

        <template v-if="hasHistory">
            <article class="grid grid-cols-3 gap-2">
                <Card class="px-3">
                    <p class="field-label">Weight</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ formatBodyValue(latestWeight) }}<span class="text-sm font-medium text-muted-foreground"> {{ preferences.weight_unit }}</span></p>
                    <p class="mt-1 flex items-center gap-1 text-sm" :class="weightDeltaClass">
                        <component :is="(trend?.delta_kg ?? 0) > 0 ? TrendingUp : TrendingDown" v-if="hasTrendDelta" :size="15" />
                        {{ hasTrendDelta ? deltaLabel(displayTrendDelta, ` ${preferences.weight_unit}`) : 'First entry' }}
                    </p>
                </Card>
                <Card class="px-3">
                    <p class="field-label">Body fat</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ latest?.body_fat_percent ?? '--' }}<span v-if="latest?.body_fat_percent !== null" class="text-sm font-medium text-muted-foreground">%</span></p>
                    <p class="mt-1 text-sm" :class="bodyFatDeltaClass">{{ hasDelta ? deltaLabel(delta?.body_fat_percent, '%') : 'First entry' }}</p>
                </Card>
                <Card class="px-3">
                    <p class="field-label">BMI</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">{{ currentBmi ?? '--' }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <Link v-if="!displayHeight" href="/settings/body-profile" class="underline underline-offset-2">Set height</Link>
                        <template v-else>{{ formatBodyValue(displayHeight) }} {{ preferences.height_unit }}</template>
                    </p>
                </Card>
            </article>
            <p v-if="energy" class="text-sm text-muted-foreground">
                <span class="font-semibold text-foreground">{{ energy.tdee.toLocaleString() }} kcal</span>
                estimated daily energy · BMR {{ energy.bmr.toLocaleString() }}
            </p>
            <Card>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="card-title">Trends</h2>
                    <div class="flex rounded-xl bg-muted p-1 dark:bg-secondary" role="group" aria-label="Trend date range">
                        <Button
                            v-for="option in rangeOptions"
                            :key="option.key"
                            type="button"
                            size="sm"
                            class="h-8 min-w-10 rounded-lg px-2.5"
                            :class="range === option.key
                                ? 'bg-primary-container text-primary-container-foreground hover:bg-primary-container dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary'
                                : 'text-foreground hover:bg-foreground/8'"
                            :variant="range === option.key ? 'default' : 'ghost'"
                            :aria-label="option.accessibleLabel"
                            :aria-pressed="range === option.key"
                            @click="visitRange(option.key)"
                        >
                            {{ option.label }}
                        </Button>
                    </div>
                </div>
                <div v-if="hasBodyFatChart" class="mt-4 flex rounded-xl bg-muted p-1 dark:bg-secondary" role="group" aria-label="Trend chart">
                    <Button
                        v-for="(label, index) in ['Weight', 'Body fat']"
                        :key="label"
                        type="button"
                        size="sm"
                        variant="ghost"
                        class="h-8 flex-1 rounded-lg"
                        :class="activeChart === index ? 'bg-card text-foreground shadow-sm hover:bg-card' : 'text-muted-foreground'"
                        :aria-pressed="activeChart === index"
                        @click="showChart(index)"
                    >
                        {{ label }}
                    </Button>
                </div>
                <div
                    ref="chartCarousel"
                    data-chart-carousel
                    class="mt-4 flex snap-x snap-mandatory gap-5 overflow-x-auto overscroll-x-contain scroll-smooth touch-pan-x motion-reduce:scroll-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    @scroll.passive="syncChartSlide"
                >
                    <div class="w-full shrink-0 snap-start" role="group" aria-label="Weight chart">
                        <div class="mb-2 flex justify-between">
                            <span class="field-label">Weight</span>
                            <span v-if="displayTargetWeight" class="text-xs text-muted-foreground">Goal {{ formatBodyValue(displayTargetWeight) }} {{ preferences.weight_unit }}</span>
                        </div>
                        <ProgressTrendChart
                            :key="`weight-${range}`"
                            :data="weightChartData"
                            :config="weightChartConfig"
                            :x-domain="chartDomain"
                            :lines="weightChartLines"
                            :dashed="['goal']"
                            :dots="['weight']"
                            :value-suffix="` ${preferences.weight_unit}`"
                        />
                        <p v-if="weightChartSummary" class="mt-3 rounded-xl bg-secondary px-3.5 py-2.5 text-sm font-semibold tabular-nums text-foreground">{{ weightChartSummary }}</p>
                    </div>
                    <div v-if="hasBodyFatChart" class="w-full shrink-0 snap-start" role="group" aria-label="Body fat chart">
                        <div class="mb-2 flex justify-between">
                            <span class="field-label">Body fat</span>
                            <span v-if="goals?.target_body_fat_percent" class="text-xs text-muted-foreground">Goal {{ goals.target_body_fat_percent }}%</span>
                        </div>
                        <ProgressTrendChart
                            :key="`body-fat-${range}`"
                            :data="bodyFatChartData"
                            :config="bodyFatChartConfig"
                            :x-domain="chartDomain"
                            :lines="bodyFatChartLines"
                            :dashed="['goal']"
                            :dots="['bodyFat']"
                            value-suffix="%"
                        />
                        <p v-if="bodyFatChartSummary" class="mt-3 rounded-xl bg-secondary px-3.5 py-2.5 text-sm font-semibold tabular-nums text-foreground">{{ bodyFatChartSummary }}</p>
                    </div>
                </div>
            </Card>
        </template>

        <Card v-if="hasMeasurements">
            <div>
                <h2 class="card-title">Body measurements</h2>
                <p class="mt-1 text-sm text-muted-foreground">Latest recorded values and change from the previous entry.</p>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-5">
                <div v-for="field in measurementFields" :key="field.key">
                    <p class="field-label">{{ field.label }}</p>
                    <p class="mt-1 text-xl font-semibold tracking-tight">
                        {{ formatBodyValue(measurementDisplay(measurements[field.key]?.value_cm)) }}
                        <span v-if="measurements[field.key]" class="text-xs font-medium text-muted-foreground">{{ preferences.measurement_unit }}</span>
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="measurements[field.key]?.delta_cm !== null && measurements[field.key]?.delta_cm !== undefined">
                            {{ deltaLabel(measurementDisplay(measurements[field.key]?.delta_cm), ` ${preferences.measurement_unit}`) }}
                        </template>
                        <template v-else-if="measurements[field.key]">First entry</template>
                        <template v-else>Not recorded</template>
                    </p>
                </div>
            </div>
        </Card>

        <Card class="space-y-4">
            <div>
                <h2 class="card-title">Log measurement</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ hasHistory ? 'Add or update a weigh-in for any day.' : 'Your first measurement unlocks trends and history.' }}
                </p>
            </div>
            <form class="space-y-3" @submit.prevent="saveMetric">
                <label class="block">
                    <span class="field-label">Date</span>
                    <Input v-model="metricForm.date" type="date" class="mt-1" />
                    <span v-if="metricForm.errors.date" class="text-sm text-destructive">{{ metricForm.errors.date }}</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="field-label">Weight {{ preferences.weight_unit }}</span>
                        <Input v-model="metricForm.weight_kg" type="number" min="1" step="0.1" class="mt-1" :placeholder="previousWeight" />
                        <span v-if="metricForm.errors.weight_kg" class="text-sm text-destructive">{{ metricForm.errors.weight_kg }}</span>
                    </label>
                    <label>
                        <span class="field-label">Body fat %</span>
                        <Input v-model="metricForm.body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" :placeholder="previousBodyFat" />
                        <span v-if="metricForm.errors.body_fat_percent" class="text-sm text-destructive">{{ metricForm.errors.body_fat_percent }}</span>
                    </label>
                </div>
                <details class="rounded-xl border border-border/60">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 px-3 py-3 text-sm font-semibold">
                        <span>Body measurements</span>
                        <span class="text-xs font-medium text-muted-foreground">Optional · {{ preferences.measurement_unit }}</span>
                    </summary>
                    <div class="grid grid-cols-2 gap-3 border-t border-border/60 p-3">
                        <label v-for="field in measurementFields" :key="field.key" :class="field.key === 'thigh_cm' ? 'col-span-2' : ''">
                            <span class="field-label">{{ field.label }} {{ preferences.measurement_unit }}</span>
                            <Input
                                v-model="metricForm[field.key]"
                                type="number"
                                :min="preferences.measurement_unit === 'cm' ? 1 : 0.4"
                                :max="preferences.measurement_unit === 'cm' ? 500 : 196.9"
                                step="0.1"
                                class="mt-1"
                                :placeholder="formatBodyValue(measurementDisplay(measurements[field.key]?.value_cm))"
                            />
                            <span v-if="metricForm.errors[field.key]" class="text-sm text-destructive">{{ metricForm.errors[field.key] }}</span>
                        </label>
                    </div>
                </details>
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-foreground">Progress photos</p>
                    <div class="grid grid-cols-3 gap-2">
                        <div v-for="pose in progressPhotoPoses" :key="pose" class="space-y-1.5">
                            <span class="field-label">{{ progressPhotoLabels[pose] }}</span>
                            <div class="relative aspect-square overflow-hidden rounded-xl bg-muted">
                                <button
                                    v-if="selectedPhotos[pose]"
                                    type="button"
                                    class="h-full w-full"
                                    :aria-label="`Retake ${progressPhotoLabels[pose].toLowerCase()} photo`"
                                    @click="openFormCamera(pose)"
                                >
                                    <img :src="selectedPhotos[pose].preview" :alt="`${progressPhotoLabels[pose]} progress`" class="h-full w-full object-cover">
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="flex h-full w-full flex-col items-center justify-center gap-1 px-2 text-muted-foreground"
                                    :aria-label="progressPhotoCaptureLabels[pose]"
                                    @click="openFormCamera(pose)"
                                >
                                    <Camera :size="20" />
                                    <span class="text-center text-xs font-medium">{{ progressPhotoCaptureLabels[pose] }}</span>
                                </button>
                                <Button
                                    v-if="selectedPhotos[pose]"
                                    type="button"
                                    size="icon"
                                    variant="inverse"
                                    class="absolute right-1 top-1 h-8 w-8"
                                    aria-label="Remove photo"
                                    @click="removeSelectedPhoto(pose)"
                                >
                                    <X :size="16" />
                                </Button>
                                <Button
                                    v-else
                                    type="button"
                                    size="icon"
                                    variant="inverse"
                                    class="absolute right-1 top-1 h-8 w-8"
                                    :aria-label="`Choose ${progressPhotoLabels[pose].toLowerCase()} from library`"
                                    @click="openLibrary(pose)"
                                >
                                    <ImageIcon :size="16" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <input
                        ref="photoInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/*"
                        class="sr-only"
                        @change="selectProgressPhotos"
                    >
                    <p v-if="cameraError" class="text-sm text-destructive">{{ cameraError }}</p>
                    <p v-if="photoUploadError" class="text-sm text-destructive">{{ photoUploadError }}</p>
                </div>
                <label class="block">
                    <span class="field-label">Notes</span>
                    <Textarea v-model="metricForm.notes" rows="3" class="mt-1" />
                </label>
                <Button
                    class="w-full"
                    :loading="metricForm.processing || photoUploading"
                    :loading-label="photoUploading ? 'Uploading photos…' : 'Saving progress…'"
                >
                    Save progress
                </Button>
            </form>
        </Card>

        <section v-if="hasHistory" class="space-y-3">
            <h2 class="text-lg font-semibold tracking-tight">Recent history</h2>
            <Card class="divide-y divide-border/60 py-2">
                <div v-for="metric in history" :key="metric.id" class="py-3 first:pt-1 last:pb-1">
                    <div class="flex items-center gap-2">
                        <p class="min-w-0 flex-1 font-semibold">{{ metric.date }}</p>
                        <p class="shrink-0 text-sm text-muted-foreground">{{ formatBodyValue(weightFromKg(metric.weight_kg, preferences.weight_unit)) }} {{ preferences.weight_unit }}<span v-if="metric.body_fat_percent !== null"> · {{ metric.body_fat_percent }}%</span></p>
                        <Button type="button" variant="ghost" size="icon-sm" class="rounded-full text-muted-foreground" aria-label="View progress photos" @pointerdown.stop @click.stop="openPhotos(metric, $event)">
                            <ImageIcon :size="18" />
                        </Button>
                        <Button variant="ghost" size="icon-sm" class="rounded-full text-muted-foreground" aria-label="Remove progress item" @click="requestDelete(metric)"><Trash2 :size="18" /></Button>
                    </div>
                    <p v-if="metric.notes" class="mt-1 text-sm text-muted-foreground">{{ metric.notes }}</p>
                    <div v-if="photoCache[metric.id]?.length" class="mt-2 flex gap-1.5">
                        <button
                            v-for="photo in photoCache[metric.id].slice(0, 3)"
                            :key="photo.id"
                            type="button"
                            class="h-12 w-12 overflow-hidden rounded-lg bg-muted"
                            :aria-label="`View ${photoPoseLabel(photo.pose).toLowerCase()} photo`"
                            @click.stop="openPhotos(metric, $event)"
                        >
                            <img :src="photo.url" :alt="photoPoseLabel(photo.pose)" class="h-full w-full object-cover">
                        </button>
                    </div>
                </div>
            </Card>
        </section>

        <AppSheet
            :open="photosMetric !== null"
            variant="drawer"
            labelled-by="progress-photos-title"
            title="Progress photos"
            description="Review or add photos for this progress measurement."
            @close="closePhotos"
        >
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
            <div v-else-if="remotePhotos.length" class="grid grid-cols-3 gap-2">
                <figure v-for="photo in remotePhotos" :key="photo.id" class="relative space-y-1">
                    <img
                        :src="photo.url"
                        :alt="photoPoseLabel(photo.pose)"
                        class="aspect-square w-full rounded-xl object-cover"
                    >
                    <Button
                        v-if="!photo.pending"
                        type="button"
                        variant="destructive"
                        size="icon-sm"
                        class="absolute right-1 top-1 rounded-full"
                        :aria-label="`Delete ${photoPoseLabel(photo.pose).toLowerCase()} photo`"
                        @click="requestPhotoDelete(photo)"
                    >
                        <Trash2 :size="15" />
                    </Button>
                    <figcaption class="text-center text-xs text-muted-foreground">{{ photoPoseLabel(photo.pose) }}</figcaption>
                </figure>
            </div>
            <div v-if="!remotePhotosLoading && remotePhotos.length < 3" class="space-y-3" :class="remotePhotos.length ? 'mt-4' : ''">
                <p v-if="remotePhotos.length === 0" class="text-sm text-muted-foreground">No photos for this measurement yet.</p>
                <p v-if="cameraError" class="text-sm text-destructive">{{ cameraError }}</p>
                <p v-if="photoUploadError" class="text-sm text-destructive">{{ photoUploadError }}</p>
                <Button type="button" class="w-full" :loading="photoUploading" loading-label="Uploading photos…" @click.stop="addPhotosForMetric">
                    Add photos
                </Button>
                <Button type="button" variant="surface" class="w-full" :disabled="photoUploading" @click.stop="addPhotosFromLibrary">
                    Choose from library
                </Button>
                <input
                    ref="sheetPhotoInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/*"
                    class="sr-only"
                    @change="selectProgressPhotos"
                >
            </div>
        </AppSheet>
        <ConfirmSheet
            :open="Boolean(pendingDelete)"
            title="Delete progress item"
            :message="pendingDelete ? `Delete progress item from ${pendingDelete.date}?` : ''"
            :processing="deleteProcessing"
            processing-label="Deleting item…"
            :error="deleteError"
            @cancel="cancelDelete"
            @confirm="confirmDelete"
        />
        <ConfirmSheet
            :open="Boolean(pendingPhotoDelete)"
            title="Delete progress photo"
            :message="pendingPhotoDelete ? `Delete the ${photoPoseLabel(pendingPhotoDelete.pose).toLowerCase()} photo?` : ''"
            :processing="deleteProcessing"
            processing-label="Deleting photo…"
            :error="deleteError"
            @cancel="cancelPhotoDelete"
            @confirm="confirmPhotoDelete"
        />

        <Teleport to="body">
            <Transition
                enter-active-class="transition-[opacity,transform] duration-sheet ease-drawer motion-reduce:duration-150 motion-reduce:transition-opacity"
                enter-from-class="translate-y-full opacity-0 motion-reduce:translate-y-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition-[opacity,transform] duration-sheet ease-drawer motion-reduce:duration-150 motion-reduce:transition-opacity"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-full opacity-0 motion-reduce:translate-y-0"
            >
                <div v-if="cameraOpen" data-motion-transform class="fixed inset-0 z-[80] flex flex-col bg-foreground text-background">
            <div class="flex items-center justify-between gap-3 bg-background px-4 py-3 text-foreground pt-[calc(env(safe-area-inset-top,0px)+0.75rem)]">
                <div class="min-w-0">
                    <p class="text-sm text-muted-foreground">{{ progressPhotoLabels[capturingPose] }} photo</p>
                    <h2 class="truncate text-xl font-semibold">
                        {{ cameraStarting ? 'Opening camera…' : activeOverlayPhoto ? (overlaySourceDate ? `Match ${progressPhotoLabels[capturingPose].toLowerCase()} · ${overlaySourceDate}` : `Match your last ${progressPhotoLabels[capturingPose].toLowerCase()}`) : `Line up your ${progressPhotoLabels[capturingPose].toLowerCase()}` }}
                    </h2>
                </div>
                <Button variant="ghost" size="icon" class="h-11 w-11 shrink-0 bg-muted text-foreground active:bg-muted/80" aria-label="Close camera" @click="closeCamera">
                    <X :size="22" />
                </Button>
            </div>

            <div class="relative min-h-0 flex-1 bg-foreground">
                <div v-if="cameraStarting || !cameraReady" class="absolute inset-0 z-20 grid place-items-center bg-foreground">
                    <LoaderCircle :size="34" class="animate-spin text-background/70" />
                </div>

                <video
                    ref="cameraVideo"
                    class="relative z-0 h-full w-full bg-foreground object-cover transition-opacity duration-150"
                    :class="[
                        cameraReady ? 'opacity-100' : 'opacity-0',
                        mirrorPreview ? '-scale-x-100' : '',
                    ]"
                    autoplay
                    muted
                    playsinline
                    disablepictureinpicture
                    controlslist="nodownload noplaybackrate noremoteplayback"
                    @loadeddata="markCameraReady"
                    @playing="markCameraReady"
                />

                <img
                    v-if="activeOverlayPhoto && overlayOpacity > 0"
                    :src="activeOverlayPhoto.url"
                    alt=""
                    class="pointer-events-none absolute inset-0 z-10 h-full w-full object-cover"
                    :style="{ opacity: overlayOpacity }"
                >

                <div class="absolute bottom-3 left-0 right-0 z-10 flex justify-center gap-2 px-4">
                    <button
                        v-for="pose in progressPhotoPoses"
                        :key="pose"
                        type="button"
                        class="inline-flex min-w-16 items-center justify-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium"
                        :class="pose === capturingPose
                            ? 'border-background bg-background text-foreground'
                            : selectedPhotos[pose]
                                ? 'border-transparent bg-background/15 text-background'
                                : 'border-dashed border-background/45 bg-transparent text-background/70'"
                        :aria-label="selectedPhotos[pose] ? `${progressPhotoLabels[pose]} photo captured` : `${progressPhotoLabels[pose]} photo, empty`"
                        :aria-pressed="pose === capturingPose"
                        @click="capturingPose = pose"
                    >
                        <Check v-if="selectedPhotos[pose]" :size="14" />
                        <Circle v-else :size="14" />
                        {{ progressPhotoLabels[pose] }}
                    </button>
                </div>
            </div>

            <div class="grid gap-3 px-4 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-3">
                <label class="block">
                    <span class="mb-1 flex justify-between text-xs text-background/70">
                        <span>{{ activeOverlayPhoto ? 'Ghost overlay' : `No previous ${progressPhotoLabels[capturingPose].toLowerCase()} photo to ghost yet` }}</span>
                        <span v-if="activeOverlayPhoto">{{ Math.round(overlayOpacity * 100) }}%</span>
                    </span>
                    <input
                        v-model.number="overlayOpacity"
                        type="range"
                        min="0"
                        max="0.7"
                        step="0.05"
                        class="w-full accent-background disabled:opacity-40"
                        aria-label="Overlay opacity"
                        :disabled="!activeOverlayPhoto"
                    >
                </label>
                <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3">
                    <Button type="button" variant="ghost" size="icon" class="h-12 w-12 bg-background/10 text-background" aria-label="Flip camera" @click="flipCamera">
                        <SwitchCamera :size="22" />
                    </Button>
                    <Button type="button" variant="inverse" class="h-14 w-full rounded-full text-base" :disabled="!cameraReady" @click="captureProgressPhoto">
                        Capture {{ progressPhotoLabels[capturingPose].toLowerCase() }}
                    </Button>
                    <Button type="button" variant="ghost" size="icon" class="h-12 w-12 bg-background/10 text-background" :aria-label="`Choose ${progressPhotoLabels[capturingPose].toLowerCase()} from library`" @click="openLibrary(capturingPose)">
                        <ImageIcon :size="22" />
                    </Button>
                </div>
            </div>
                </div>
            </Transition>
        </Teleport>
    </section>
</template>
