<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Barcode, Camera, Pencil, Dumbbell, LoaderCircle, Plus, Search, Utensils, History, X } from '@lucide/vue';
import { formatDisplayDate } from '../dateFormat';
import { hapticImpact } from '../haptics';
import { resizePhoto } from '../photoResize';
import MacroSummary from '../Components/Add/MacroSummary.vue';
import AppSheet from '../Components/AppSheet.vue';
import Card from "../Components/Card.vue";
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';
import SelectContent from '../Components/ui/select/SelectContent.vue';
import SelectItem from '../Components/ui/select/SelectItem.vue';
import SelectTrigger from '../Components/ui/select/SelectTrigger.vue';
import SelectValue from '../Components/ui/select/SelectValue.vue';
import Textarea from '../Components/ui/textarea/Textarea.vue';

type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';
type MacroField = 'protein_g' | 'carbs_g' | 'fat_g';

interface FoodProduct {
    type?: 'product';
    id: string;
    barcode: string;
    name: string;
    brand?: string | null;
    image_url?: string | null;
    nutrition_unit?: string | null;
    calories_per_100: number;
    protein_per_100: number;
    carbs_per_100: number;
    fat_per_100: number;
}

interface PreviousMeal {
    type: 'previous_meal';
    id: string;
    name: string;
    brand?: string | null;
    image_url?: string | null;
    portion_quantity: number | null;
    portion_unit: string | null;
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

type FoodSearchResult = FoodProduct | PreviousMeal;

interface PortionOption {
    label?: string;
    quantity: number;
    unit: string;
}

interface AnalysisContext {
    id: string;
    confidence: number;
    recognized_components: string[];
}

interface MealAnalysisDraft {
    name: string;
    portion_quantity: number;
    portion_unit: string;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    confidence: number;
    recognized_components: string[];
}

interface SelectedPhoto {
    file: File;
    preview: string;
}

interface NativeBridge {
    Scanner: {
        scan(): {
            prompt(message: string): ReturnType<NativeBridge['Scanner']['scan']>;
            formats(formats: string[]): ReturnType<NativeBridge['Scanner']['scan']>;
            id(id: string): Promise<void>;
        };
    };
    On(eventName: string, callback: (payload: unknown, eventName: string) => void): void;
    Off(eventName: string, callback: (payload: unknown, eventName: string) => void): void;
    Events: {
        Scanner: {
            CodeScanned: string;
        };
    };
}

const props = withDefaults(defineProps<{
    date: string;
    mealTypes: MealType[];
    mode: string;
    meal?: MealType | null;
    autoScan?: boolean;
    previousFoodEntries?: PreviousMeal[];
    previousCustomMeals?: PreviousMeal[];
    previousBreakfastMeals?: PreviousMeal[];
}>(), {
    meal: null,
    autoScan: false,
    previousFoodEntries: () => [],
    previousCustomMeals: () => [],
    previousBreakfastMeals: () => [],
});

const mealLabels: Record<MealType, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

const customMealMacroFields: ReadonlyArray<readonly [MacroField, string]> = [
    ['protein_g', 'Protein'],
    ['carbs_g', 'Carbs'],
    ['fat_g', 'Fat'],
];

function currentTime() {
    return new Date().toTimeString().slice(0, 5);
}

function addModeUrl(mode: string, extra: Record<string, string> = {}) {
    const params = new URLSearchParams({
        date: props.date,
        mode,
        ...extra,
    });

    return `/add?${params.toString()}`;
}

function smartMealType(): MealType {
    const hour = new Date().getHours();

    if (hour < 10) return 'breakfast';
    if (hour < 14) return 'lunch';
    if (hour < 20) return 'dinner';

    return 'snacks';
}

const selectedMealType = ref<MealType>(smartMealType());
const barcode = ref('');
const lookupError = ref('');
const lookupLoading = ref(false);
const nativeMessage = ref('');
const nativeBridge = ref<NativeBridge | null>(null);
const scannerStarting = ref(false);
const webScannerOpen = ref(false);
const webScannerReady = ref(false);
const webScannerVideo = ref<HTMLVideoElement | null>(null);
const webScannerControls = ref<{ stop(): void } | null>(null);
const product = ref<FoodProduct | null>(null);
const portionOptions = ref<PortionOption[]>([]);
const selectedPortionKey = ref('');
const manualBarcodeOpen = ref(false);
const foodSearch = ref('');
const foodSearchLoading = ref(false);
const foodSearchResults = ref<FoodSearchResult[]>([]);
const selectedPreviousMeal = ref<PreviousMeal | null>(null);
const previousMealPortionQuantity = ref<number | null>(null);
const previousMealPortionUnit = ref('g');
let foodSearchRequestId = 0;
const photoInput = ref<HTMLInputElement | null>(null);
const selectedPhotos = ref<SelectedPhoto[]>([]);
const photoNote = ref('');
const photoAnalysisLoading = ref(false);
const photoAnalysisError = ref('');
const analysisContext = ref<AnalysisContext | null>(null);
const analysisFollowUpOpen = ref(false);
const analysisFollowUp = ref('');
const analysisFollowUpLoading = ref(false);
const analysisFollowUpError = ref('');

const customMealForm = useForm({
    date: props.date,
    meal_type: selectedMealType.value,
    name: '',
    portion_quantity: 100,
    portion_unit: 'g',
    protein_g: '',
    carbs_g: '',
    fat_g: '',
    analysis_id: '',
});

const barcodeMealForm = useForm({
    date: props.date,
    meal_type: selectedMealType.value,
    food_product_id: '',
    portion_quantity: 100,
    portion_unit: 'g',
});

const workoutForm = useForm({
    date: props.date,
    title: '',
    calories_burned: '',
    time: currentTime(),
});

const customCalories = computed(() => {
    return Math.round((Number(customMealForm.protein_g) * 4) + (Number(customMealForm.carbs_g) * 4) + (Number(customMealForm.fat_g) * 9));
});

const barcodeCalories = computed(() => {
    if (!product.value) return 0;

    return Math.round(Number(product.value.calories_per_100) * (Number(barcodeMealForm.portion_quantity) / 100));
});

const barcodePortionMacros = computed(() => {
    if (!product.value) {
        return {
            calories: 0,
            protein_g: 0,
            carbs_g: 0,
            fat_g: 0,
        };
    }

    const factor = Math.max(Number(barcodeMealForm.portion_quantity) || 0, 0) / 100;

    return {
        calories: barcodeCalories.value,
        protein_g: roundMacro(Number(product.value.protein_per_100) * factor),
        carbs_g: roundMacro(Number(product.value.carbs_per_100) * factor),
        fat_g: roundMacro(Number(product.value.fat_per_100) * factor),
    };
});

const previousMealHasPortion = computed(() => {
    return selectedPreviousMeal.value?.portion_quantity !== null && selectedPreviousMeal.value?.portion_quantity !== undefined;
});

const previousMealCalories = computed(() => {
    if (!selectedPreviousMeal.value) return 0;

    if (!previousMealHasPortion.value || !previousMealPortionQuantity.value) {
        return selectedPreviousMeal.value.calories;
    }

    return Math.round(Number(selectedPreviousMeal.value.calories) * (Number(previousMealPortionQuantity.value) / Number(selectedPreviousMeal.value.portion_quantity)));
});

const previousMealPortionMacros = computed(() => {
    if (!selectedPreviousMeal.value) {
        return {
            calories: 0,
            protein_g: 0,
            carbs_g: 0,
            fat_g: 0,
        };
    }

    if (!previousMealHasPortion.value || !previousMealPortionQuantity.value) {
        return {
            calories: selectedPreviousMeal.value.calories,
            protein_g: Number(selectedPreviousMeal.value.protein_g),
            carbs_g: Number(selectedPreviousMeal.value.carbs_g),
            fat_g: Number(selectedPreviousMeal.value.fat_g),
        };
    }

    const factor = Number(previousMealPortionQuantity.value) / Number(selectedPreviousMeal.value.portion_quantity);

    return {
        calories: previousMealCalories.value,
        protein_g: roundMacro(Number(selectedPreviousMeal.value.protein_g) * factor),
        carbs_g: roundMacro(Number(selectedPreviousMeal.value.carbs_g) * factor),
        fat_g: roundMacro(Number(selectedPreviousMeal.value.fat_g) * factor),
    };
});

const activeFoodMacros = computed(() => {
    if (selectedPreviousMeal.value) {
        return previousMealPortionMacros.value;
    }

    return barcodePortionMacros.value;
});

const activeFoodCalories = computed(() => activeFoodMacros.value.calories);
const activeFoodUnit = computed(() => product.value?.nutrition_unit || previousMealPortionUnit.value || 'g');
const activeFoodHasPortion = computed(() => Boolean(product.value || previousMealHasPortion.value));
const activeFoodPortionQuantity = computed({
    get() {
        return selectedPreviousMeal.value ? previousMealPortionQuantity.value : barcodeMealForm.portion_quantity;
    },
    set(value: number | null) {
        if (selectedPreviousMeal.value) {
            previousMealPortionQuantity.value = value;
            return;
        }

        barcodeMealForm.portion_quantity = value ?? 0;
    },
});
const activeFoodPortionUnit = computed({
    get() {
        return selectedPreviousMeal.value ? previousMealPortionUnit.value : barcodeMealForm.portion_unit;
    },
    set(value: string) {
        if (selectedPreviousMeal.value) {
            previousMealPortionUnit.value = value;
            return;
        }

        barcodeMealForm.portion_unit = value;
    },
});
const activeFoodPortionOptions = computed<PortionOption[]>(() => {
    if (product.value) {
        return portionOptions.value;
    }

    if (!selectedPreviousMeal.value || !previousMealHasPortion.value || selectedPreviousMeal.value.portion_quantity === null) {
        return [];
    }

    return [{
        label: `${formatMacro(Number(selectedPreviousMeal.value.portion_quantity))}${selectedPreviousMeal.value.portion_unit || previousMealPortionUnit.value}`,
        quantity: selectedPreviousMeal.value.portion_quantity,
        unit: selectedPreviousMeal.value.portion_unit || previousMealPortionUnit.value,
    }];
});

const displayDate = computed(() => formatDisplayDate(props.date));
const foodSearchQuery = computed(() => foodSearch.value.trim());
const foodAddSheetOpen = computed(() => Boolean(product.value || selectedPreviousMeal.value));

function setMealType(mealType: MealType) {
    selectedMealType.value = mealType;
    customMealForm.meal_type = mealType;
    barcodeMealForm.meal_type = mealType;
}

function selectPortion(option: PortionOption, index: number) {
    selectedPortionKey.value = String(index);
    activeFoodPortionQuantity.value = option.quantity;
    activeFoodPortionUnit.value = option.unit;
}

function roundMacro(value: number) {
    return Math.round(value * 10) / 10;
}

function formatMacro(value: number) {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

function portionOptionLabel(option: PortionOption) {
    return option.label || `${option.quantity}${option.unit}`;
}

async function lookup(scannedBarcode: string | null = null) {
    lookupError.value = '';
    lookupLoading.value = true;
    selectedPreviousMeal.value = null;

    try {
        const response = await axios.post('/barcode/lookup', {
            barcode: scannedBarcode || barcode.value,
        });

        product.value = response.data.product;
        portionOptions.value = response.data.portion_options || [];
        barcode.value = response.data.product.barcode;
        barcodeMealForm.food_product_id = response.data.product.id;
        barcodeMealForm.portion_unit = response.data.product.nutrition_unit || 'g';
        hapticImpact();

        if (portionOptions.value.length > 0) {
            selectPortion(portionOptions.value[0], 0);
        }
    } catch (error) {
        product.value = null;
        const errors = axios.isAxiosError(error) ? error.response?.data?.errors : null;
        lookupError.value = errors?.barcode?.[0] || 'Could not look up that barcode. Add it as custom food instead.';
    } finally {
        lookupLoading.value = false;
    }
}

async function searchFoodProducts() {
    const query = foodSearch.value.trim();
    const requestId = ++foodSearchRequestId;

    if (query.length < 2) {
        foodSearchResults.value = [];
        foodSearchLoading.value = false;
        return;
    }

    foodSearchLoading.value = true;

    try {
        const { data } = await axios.get('/food-products/search', {
            params: { q: query },
        });

        if (requestId === foodSearchRequestId) {
            foodSearchResults.value = data.products || [];
        }
    } catch {
        if (requestId === foodSearchRequestId) {
            foodSearchResults.value = [];
        }
    } finally {
        if (requestId === foodSearchRequestId) {
            foodSearchLoading.value = false;
        }
    }
}

function selectFoodProduct(foodProduct: FoodProduct) {
    selectedPreviousMeal.value = null;
    product.value = foodProduct;
    portionOptions.value = [
        { label: `100${foodProduct.nutrition_unit || 'g'}`, quantity: 100, unit: foodProduct.nutrition_unit || 'g' },
    ];
    barcode.value = foodProduct.barcode;
    barcodeMealForm.food_product_id = foodProduct.id;
    barcodeMealForm.portion_unit = foodProduct.nutrition_unit || 'g';
    selectPortion(portionOptions.value[0], 0);
    hapticImpact();
}

function selectPreviousMeal(meal: PreviousMeal) {
    selectedPreviousMeal.value = meal;
    product.value = null;
    previousMealPortionQuantity.value = meal.portion_quantity;
    previousMealPortionUnit.value = meal.portion_unit || 'g';
    selectedPortionKey.value = meal.portion_quantity ? '0' : '';
    hapticImpact();
}

function selectFoodResult(result: FoodSearchResult) {
    if (result.type === 'previous_meal') {
        selectPreviousMeal(result);
        return;
    }

    selectFoodProduct(result);
}

async function startScan() {
    nativeMessage.value = '';
    scannerStarting.value = true;
    manualBarcodeOpen.value = false;

    if (nativeBridge.value) {
        try {
            await nativeBridge.value.Scanner.scan()
                .prompt('Scan food barcode')
                .formats(['ean13', 'ean8', 'upca', 'upce', 'code128'])
                .id('product-scanner');

            return;
        } catch {
            // Fall through to the web camera scanner when the native scanner plugin is unavailable.
        } finally {
            scannerStarting.value = false;
        }
    }

    await startWebScan();
}

function showManualBarcodeInput() {
    webScannerControls.value?.stop();
    webScannerControls.value = null;
    webScannerReady.value = false;
    manualBarcodeOpen.value = true;
    webScannerOpen.value = true;
    scannerStarting.value = false;
}

async function startWebScan() {
    stopWebScan();
    nativeMessage.value = '';
    scannerStarting.value = true;
    webScannerOpen.value = true;
    webScannerReady.value = false;

    await nextTick();

    if (!navigator.mediaDevices?.getUserMedia) {
        webScannerOpen.value = false;
        nativeMessage.value = 'Camera scanning is not available on this device. Enter the barcode manually.';
        scannerStarting.value = false;
        return;
    }

    try {
        const cameraConstraints = {
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
            },
        };
        const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
            import('@zxing/browser'),
            import('@zxing/library'),
        ]);
        const stream = await navigator.mediaDevices.getUserMedia(cameraConstraints);
        const hints = new Map();

        hints.set(DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13,
            BarcodeFormat.EAN_8,
            BarcodeFormat.UPC_A,
            BarcodeFormat.UPC_E,
            BarcodeFormat.CODE_128,
        ]);

        const reader = new BrowserMultiFormatReader(hints, {
            delayBetweenScanAttempts: 150,
            delayBetweenScanSuccess: 300,
        });
        const controls = await reader.decodeFromStream(
            stream,
            webScannerVideo.value,
            (result) => {
                const scanned = result?.getText();

                if (!scanned) return;

                barcode.value = scanned;
                stopWebScan();
                lookup(scanned);
            },
        );

        webScannerControls.value = controls;
    } catch (error) {
        stopWebScan();
        nativeMessage.value = cameraErrorMessage(error);
    } finally {
        scannerStarting.value = false;
    }
}

function stopWebScan() {
    webScannerControls.value?.stop();
    webScannerControls.value = null;
    webScannerReady.value = false;
    webScannerOpen.value = false;
    manualBarcodeOpen.value = false;
}

function markWebScannerReady() {
    webScannerReady.value = true;
}

function cameraErrorMessage(error: unknown) {
    const errorName = error instanceof DOMException || error instanceof Error ? error.name : null;

    if (errorName === 'NotAllowedError' || errorName === 'SecurityError') {
        return 'Camera permission was denied. Allow camera access for Buff, or enter the barcode manually.';
    }

    if (errorName === 'NotFoundError' || errorName === 'OverconstrainedError') {
        return 'No usable camera was found. Enter the barcode manually.';
    }

    return 'Scanner could not start. Enter the barcode manually.';
}

function handleScan(payload: unknown) {
    const scanPayload = typeof payload === 'object' && payload !== null ? payload as { id?: string; data?: string } : null;

    if (scanPayload?.id && scanPayload.id !== 'product-scanner') return;

    const scanned = scanPayload?.data || (typeof payload === 'string' ? payload : null);

    if (scanned) {
        barcode.value = scanned;
        lookup(scanned);
    }
}

function addBarcodeMeal() {
    barcodeMealForm.meal_type = selectedMealType.value;
    hapticImpact();
    barcodeMealForm.post('/meals/barcode');
}

function addPreviousMeal() {
    if (!selectedPreviousMeal.value) return;

    hapticImpact();

    const payload: {
        date: string;
        meal_type: MealType;
        portion_quantity?: number | null;
        portion_unit?: string;
    } = {
        date: props.date,
        meal_type: selectedMealType.value,
    };

    if (previousMealHasPortion.value) {
        payload.portion_quantity = previousMealPortionQuantity.value;
        payload.portion_unit = previousMealPortionUnit.value;
    }

    router.post(`/meals/${selectedPreviousMeal.value.id}/repeat`, payload, { preserveScroll: true });
}

function closeFoodAddSheet() {
    selectedPreviousMeal.value = null;
    previousMealPortionQuantity.value = null;
    previousMealPortionUnit.value = 'g';
    product.value = null;
    selectedPortionKey.value = '';
}

function addCustomMeal() {
    customMealForm.meal_type = selectedMealType.value;
    hapticImpact();
    customMealForm
        .transform((data) => ({
            ...data,
            protein_g: Number(data.protein_g || 0),
            carbs_g: Number(data.carbs_g || 0),
            fat_g: Number(data.fat_g || 0),
        }))
        .post('/meals/custom');
}

async function selectPhotos(event: Event) {
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

function removePhoto(index: number) {
    const [photo] = selectedPhotos.value.splice(index, 1);

    if (photo) {
        URL.revokeObjectURL(photo.preview);
    }
}

function applyAnalysisDraft(analysis: {id: string; draft: MealAnalysisDraft}) {
    const draft = analysis.draft;

    analysisContext.value = {
        id: analysis.id,
        confidence: Number(draft.confidence || 0),
        recognized_components: Array.isArray(draft.recognized_components) ? draft.recognized_components : [],
    };
    customMealForm.name = draft.name;
    customMealForm.portion_quantity = Number(draft.portion_quantity);
    customMealForm.portion_unit = draft.portion_unit;
    customMealForm.protein_g = Number(draft.protein_g);
    customMealForm.carbs_g = Number(draft.carbs_g);
    customMealForm.fat_g = Number(draft.fat_g);
    customMealForm.analysis_id = analysis.id;
    customMealForm.clearErrors();
}

async function analyzePhotos() {
    if (selectedPhotos.value.length === 0) {
        photoAnalysisError.value = 'Add at least one meal photo.';
        return;
    }

    photoAnalysisLoading.value = true;
    photoAnalysisError.value = '';
    const data = new FormData();
    selectedPhotos.value.forEach(({file}) => data.append('photos[]', file));
    data.append('note', photoNote.value);

    try {
        const response = await axios.post('/meal-analyses', data);
        const analysis = response.data.analysis;
        const draft = analysis?.draft;

        if (!analysis?.id || !draft) {
            throw new Error('Invalid analysis');
        }

        applyAnalysisDraft(analysis);
    } catch (error) {
        const code = axios.isAxiosError(error) ? error.response?.data?.code : null;
        photoAnalysisError.value = {
            meal_analysis_quota_reached: 'Today’s photo-analysis limit has been reached.',
            meal_analysis_in_progress: 'Another meal analysis is still running.',
            invalid_meal_analysis: 'The photos did not produce a usable estimate. Try clearer photos.',
            meal_analysis_unavailable: 'Meal analysis is temporarily unavailable.',
        }[code] || (axios.isAxiosError(error) ? error.response?.data?.message : null) || 'Could not analyze these photos. Check your connection.';
    } finally {
        photoAnalysisLoading.value = false;
    }
}

async function followUpAnalysis() {
    const correction = analysisFollowUp.value.trim();
    const id = analysisContext.value?.id;

    if (!correction || !id) {
        analysisFollowUpError.value = 'Describe what the estimate got wrong.';
        return;
    }

    analysisFollowUpLoading.value = true;
    analysisFollowUpError.value = '';

    try {
        const response = await axios.post(`/meal-analyses/${id}/follow-up`, {correction});
        const analysis = response.data.analysis;

        if (!analysis?.id || !analysis?.draft) {
            throw new Error('Invalid analysis');
        }

        applyAnalysisDraft(analysis);
        analysisFollowUpLoading.value = false;
        closeFollowUpModal();
        window.dispatchEvent(new CustomEvent('buff:toast', {detail: 'Estimate updated.'}));
    } catch (error) {
        analysisFollowUpError.value = axios.isAxiosError(error)
            ? error.response?.data?.errors?.correction?.[0] || error.response?.data?.message || 'Could not update the estimate.'
            : 'Could not update the estimate.';
    } finally {
        analysisFollowUpLoading.value = false;
    }
}

function openFollowUpModal() {
    analysisFollowUpError.value = '';
    analysisFollowUpOpen.value = true;
}

function closeFollowUpModal() {
    if (analysisFollowUpLoading.value) {
        return;
    }

    analysisFollowUpOpen.value = false;
    analysisFollowUp.value = '';
    analysisFollowUpError.value = '';
}

async function cancelAnalysis() {
    const id = analysisContext.value?.id;

    if (id) {
        try {
            await axios.delete(`/meal-analyses/${id}`);
        } catch {
            // Drafts expire server-side if cancellation cannot get online.
        }
    }

    analysisContext.value = null;
    customMealForm.analysis_id = '';
}

function addWorkout() {
    hapticImpact();
    workoutForm.post('/workouts');
}

function selectPreviousCustomMeal(meal: PreviousMeal) {
    customMealForm.name = meal.name;
    customMealForm.portion_quantity = meal.portion_quantity ?? 100;
    customMealForm.portion_unit = meal.portion_unit || 'g';
    customMealForm.protein_g = meal.protein_g;
    customMealForm.carbs_g = meal.carbs_g;
    customMealForm.fat_g = meal.fat_g;
    customMealForm.clearErrors();
}

onMounted(async () => {
    try {
        const native = await import('#nativephp');
        nativeBridge.value = native;
        native.On(native.Events.Scanner.CodeScanned, handleScan);
    } catch {
        nativeBridge.value = null;
    }

    if (props.mode === 'food' && props.autoScan) {
        startScan();
    }

    if (props.meal){
        selectedMealType.value = props.meal;
    }
});

onUnmounted(() => {
    stopWebScan();
    selectedPhotos.value.forEach(({preview}) => URL.revokeObjectURL(preview));

    if (nativeBridge.value) {
        nativeBridge.value.Off(nativeBridge.value.Events.Scanner.CodeScanned, handleScan);
    }
});
</script>

<template>
    <Head title="Add" />

    <section class="space-y-5">
        <PageHeader :kicker="displayDate">
            {{ mode === 'food' ? 'Add food' : mode === 'custom' ? 'Custom food' : mode === 'photo' ? 'Photo meal' : mode === 'workout' ? 'Workout' : 'Add' }}
            <span v-if="meal"> — {{ meal }}</span>
        </PageHeader>

        <div v-if="webScannerOpen" class="fixed inset-0 z-50 flex flex-col bg-foreground text-primary-foreground">
            <div class="flex items-center justify-between gap-3 px-4 py-3 pt-[calc(env(safe-area-inset-top,0px)+0.75rem)]">
                <div class="min-w-0">
                    <p class="text-sm text-primary-foreground/70">{{ manualBarcodeOpen ? 'Manual barcode' : 'Scan barcode' }}</p>
                    <h2 class="truncate text-xl font-semibold">{{ scannerStarting ? 'Opening camera...' : manualBarcodeOpen ? 'Enter barcode' : 'Point camera at barcode' }}</h2>
                </div>
                <Button variant="ghost" size="icon" class="h-11 w-11 shrink-0 bg-primary-foreground/10 text-primary-foreground active:bg-primary-foreground/15" aria-label="Close scanner" @click="stopWebScan">
                    <X :size="22" />
                </Button>
            </div>

            <div class="relative min-h-0 flex-1">
                <div v-if="!manualBarcodeOpen && !webScannerReady" class="absolute inset-0 z-10 grid h-full w-full place-items-center bg-foreground">
                    <LoaderCircle :size="34" class="animate-spin text-primary-foreground/70" />
                </div>

                <video
                    v-show="!manualBarcodeOpen"
                    ref="webScannerVideo"
                    class="scanner-video h-full w-full bg-foreground object-cover transition-opacity duration-150"
                    :class="webScannerReady ? 'opacity-100' : 'opacity-0'"
                    autoplay
                    muted
                    playsinline
                    disablepictureinpicture
                    controlslist="nodownload noplaybackrate noremoteplayback"
                    @loadeddata="markWebScannerReady"
                    @playing="markWebScannerReady"
                />

                <div v-if="manualBarcodeOpen" class="flex h-full items-center px-4">
                    <div class="w-full rounded-xl bg-card p-4 text-foreground">
                        <label class="block">
                            <span class="field-label">Barcode</span>
                            <Input
                                v-model="barcode"
                                type="text"
                                inputmode="numeric"
                                placeholder="Enter barcode"
                                class="mt-1"
                            />
                        </label>
                        <Button class="mt-3 w-full" :disabled="lookupLoading" @click="lookup(); stopWebScan()">
                            <Search :size="19" />
                            Look up barcode
                        </Button>
                    </div>
                </div>
            </div>

            <div class="grid gap-2 px-4 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-3">
                <Button v-if="!manualBarcodeOpen" variant="inverse" class="w-full" @click="showManualBarcodeInput">
                    Enter barcode manually
                </Button>
            </div>
        </div>

        <article v-if="mode === 'choose'" class="grid gap-3">
            <Button :as="Link" :href="addModeUrl('food')" variant="outline" class="h-auto justify-start rounded-2xl p-4 text-left">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary text-primary-foreground">
                    <Utensils :size="22" />
                </span>
                <span>
                    <span class="block font-semibold">Food</span>
                    <span class="block text-sm font-medium text-muted-foreground">Search, scan, or custom</span>
                </span>
            </Button>

            <Button :as="Link" :href="addModeUrl('workout')" variant="outline" class="h-auto justify-start rounded-2xl p-4 text-left">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-workout text-primary-foreground">
                    <Dumbbell :size="22" />
                </span>
                <span>
                    <span class="block font-semibold">Workout</span>
                    <span class="block text-sm font-medium text-muted-foreground">Log calories burned</span>
                </span>
            </Button>

            <Button
                :as="Link"
                :href="addModeUrl('photo')"
                variant="outline"
                class="h-auto justify-start rounded-2xl p-4 text-left"
            >
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-food text-primary-foreground">
                    <Camera :size="22" />
                </span>
                <span>
                    <span class="block font-semibold">Photo meal</span>
                    <span class="block text-sm font-medium text-muted-foreground">Estimate editable macros</span>
                </span>
            </Button>
        </article>

        <Card v-if="mode === 'photo' && !analysisContext">
            <div class="flex items-center gap-2">
                <Camera :size="21" class="text-food" />
                <h2 class="font-semibold">Analyze meal photos</h2>
            </div>
            <p class="mt-2 text-sm text-muted-foreground">Add up to three clear angles. Nothing is logged until you review and save.</p>

            <div v-if="selectedPhotos.length" class="mt-4 grid grid-cols-3 gap-2">
                <div v-for="(photo, index) in selectedPhotos" :key="photo.preview" class="relative aspect-square overflow-hidden rounded-xl bg-muted">
                    <img :src="photo.preview" alt="Selected meal" class="h-full w-full object-cover">
                    <Button type="button" size="icon" variant="inverse" class="absolute right-1 top-1 h-8 w-8" aria-label="Remove photo" @click="removePhoto(index)">
                        <X :size="16" />
                    </Button>
                </div>
            </div>

            <input
                ref="photoInput"
                type="file"
                accept="image/*"
                capture="environment"
                multiple
                class="hidden"
                @change="selectPhotos"
            >
            <Button
                v-if="selectedPhotos.length < 3"
                type="button"
                variant="surface"
                class="mt-4 w-full"
                @click="photoInput?.click()"
            >
                <Camera :size="18" />
                {{ selectedPhotos.length ? 'Add another' : 'Take or choose photo' }}
            </Button>

            <label class="mt-4 block">
                <span class="field-label">Context (optional)</span>
                <Textarea v-model="photoNote" maxlength="1000" rows="3" class="mt-1" placeholder="Sauce, hidden ingredients, or portion notes" />
            </label>

            <p v-if="photoAnalysisError" class="mt-3 rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert">
                {{ photoAnalysisError }}
            </p>

            <Button type="button" class="mt-4 w-full" :disabled="photoAnalysisLoading || selectedPhotos.length === 0" @click="analyzePhotos">
                <LoaderCircle v-if="photoAnalysisLoading" :size="18" class="animate-spin" />
                {{ photoAnalysisLoading ? 'Analyzing meal…' : 'Analyze meal' }}
            </Button>
        </Card>

        <div v-if="photoAnalysisLoading" class="fixed inset-0 z-50 grid place-items-center bg-background/85 px-6 backdrop-blur" role="status" aria-live="polite">
            <div class="text-center">
                <LoaderCircle :size="36" class="mx-auto animate-spin text-primary" />
                <p class="mt-3 font-semibold">Analyzing your meal…</p>
                <p class="mt-1 text-sm text-muted-foreground">Keep Buff open while the estimate is prepared.</p>
            </div>
        </div>

        <Card v-if="mode === 'food'">
            <div class="flex items-center gap-2">
                <Search :size="21" class="text-fat" />
                <h2 class="font-semibold">Food</h2>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2">
                <Button variant="default" class="h-auto w-full flex-col gap-2 rounded-2xl px-2 py-3">
                    <Search :size="22" />
                    <span class="text-sm font-semibold">Search</span>
                </Button>
                <Button
                    variant="outline"
                    class="h-auto w-full flex-col gap-2 rounded-2xl px-2 py-3"
                    :disabled="scannerStarting"
                    @click="startScan"
                >
                    <Camera :size="22" />
                    <span class="text-sm font-semibold">{{ scannerStarting ? 'Opening...' : 'Scan' }}</span>
                </Button>
                <Button
                    :as="Link"
                    :href="`/add?date=${date}&mode=custom`"
                    variant="outline"
                    class="h-auto w-full flex-col gap-2 rounded-2xl px-2 py-3"
                >
                    <Pencil :size="22" />
                    <span class="text-sm font-semibold">Custom</span>
                </Button>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="searchFoodProducts">
                <Input
                    v-model="foodSearch"
                    type="search"
                    placeholder="Search..."
                    class="min-w-0 flex-1"
                />
                <Button class="aspect-square h-[50px] shrink-0 px-0 py-0" :disabled="foodSearchLoading" aria-label="Search foods">
                    <LoaderCircle v-if="foodSearchLoading" :size="21" class="animate-spin" />
                    <Search v-else :size="21" />
                </Button>
            </form>

            <p v-if="nativeMessage" class="mt-3 rounded-xl bg-muted p-3 text-sm text-foreground/80">{{ nativeMessage }}</p>
            <p v-if="lookupError" class="mt-3 rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground">{{ lookupError }}</p>

            <div v-if="foodSearchLoading" class="mt-4 flex items-center gap-2 rounded-xl bg-muted p-3 text-sm text-muted-foreground" role="status" aria-live="polite">
                <LoaderCircle :size="17" class="animate-spin text-primary" />
                Searching...
            </div>

            <div v-else-if="foodSearchResults.length" class="mt-4 grid gap-2">
                <Button
                    v-for="result in foodSearchResults"
                    :key="result.id"
                    type="button"
                    variant="surface"
                    class="h-auto w-full min-w-0 justify-start overflow-hidden p-3 text-left"
                    @click="selectFoodResult(result)"
                >
                    <img v-if="result.image_url" :src="result.image_url" alt="" class="h-12 w-12 shrink-0 rounded-xl object-cover">
                    <span v-else class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground">
                        <Utensils v-if="result.type === 'previous_meal'" :size="20" />
                        <Barcode v-else :size="20" />
                    </span>
                    <span class="min-w-0 flex-1 flex items-center gap-2 overflow-hidden">
                        <History v-if="result.type === 'previous_meal'" :size="20" />
                        <span class="block truncate font-semibold">
                            {{ result.name }}
                        </span>
                    </span>
                </Button>
            </div>

            <div v-else-if="!foodSearchQuery && previousFoodEntries.length" class="mt-4">
                <p class="field-label">Previous food entries</p>
                <div class="mt-2 grid gap-2">
                    <Button
                        v-for="entry in previousFoodEntries"
                        :key="entry.id"
                        type="button"
                        variant="surface"
                        class="h-auto w-full min-w-0 justify-start overflow-hidden p-3 text-left"
                        @click="selectPreviousMeal(entry)"
                    >
                        <img v-if="entry.image_url" :src="entry.image_url" alt="" class="h-12 w-12 shrink-0 rounded-xl object-cover">
                        <span v-else class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground">
                            <Utensils :size="20" />
                        </span>
                        <span class="min-w-0 flex-1 overflow-hidden">
                            <span class="block truncate font-semibold">{{ entry.name }}</span>
                            <span class="block truncate text-sm text-muted-foreground">
                                <span v-if="entry.portion_quantity">{{ entry.portion_quantity }}{{ entry.portion_unit }}</span>
                            </span>
                        </span>
                    </Button>
                </div>
            </div>

            <p v-else-if="foodSearchQuery.length >= 2 && !foodSearchLoading" class="mt-4 rounded-xl bg-muted p-3 text-sm text-muted-foreground">
                No products found.
            </p>
        </Card>

        <AppSheet
            :open="foodAddSheetOpen"
            labelled-by="food-add-title"
            variant="drawer"
            class="max-h-[88vh] px-6 pt-6"
            @close="closeFoodAddSheet"
        >
            <Button variant="ghost" size="icon" class="absolute right-4 top-4 h-10 w-10 shrink-0" aria-label="Close add food" @click="closeFoodAddSheet">
                <X :size="24" />
            </Button>

            <div v-if="selectedPreviousMeal || product" class="space-y-5">
                <div class="flex gap-4">
                    <img v-if="(selectedPreviousMeal || product)?.image_url" :src="(selectedPreviousMeal || product)?.image_url || ''" alt="" class="h-20 w-20 rounded-xl object-cover">
                    <span v-else class="grid size-20 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground">
                        <Utensils v-if="selectedPreviousMeal" :size="26" />
                        <Barcode v-else :size="26" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p id="food-add-title" class="truncate text-lg font-semibold text-foreground">{{ selectedPreviousMeal?.name || product?.name || 'Add food' }}</p>
                        <p class="truncate text-base text-muted-foreground">{{ selectedPreviousMeal?.brand || product?.brand || (selectedPreviousMeal ? 'Previous item' : 'Saved product') }}</p>
                        <p v-if="selectedPreviousMeal" class="mt-1 text-sm text-foreground">
                            {{ selectedPreviousMeal.calories }} kcal<span v-if="selectedPreviousMeal.portion_quantity"> · {{ formatMacro(Number(selectedPreviousMeal.portion_quantity)) }}{{ selectedPreviousMeal.portion_unit }}</span> · P {{ formatMacro(Number(selectedPreviousMeal.protein_g)) }}g · C {{ formatMacro(Number(selectedPreviousMeal.carbs_g)) }}g · F {{ formatMacro(Number(selectedPreviousMeal.fat_g)) }}g
                        </p>
                        <p v-else-if="product" class="mt-1 text-sm text-foreground">
                            {{ product.calories_per_100 }} kcal · P {{ formatMacro(Number(product.protein_per_100)) }}g · C {{ formatMacro(Number(product.carbs_per_100)) }}g · F {{ formatMacro(Number(product.fat_per_100)) }}g / 100{{ product.nutrition_unit || 'g' }}
                        </p>
                    </div>
                </div>

                <div v-if="activeFoodHasPortion" class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                    <div v-if="activeFoodPortionOptions.length" class="col-span-2 flex gap-2 overflow-x-auto pb-1">
                        <Button
                            v-for="(option, index) in activeFoodPortionOptions"
                            :key="`${option.quantity}-${option.unit}-${index}`"
                            type="button"
                            class="h-auto shrink-0 px-4 py-1.5 text-lg"
                            :variant="selectedPortionKey === String(index) ? 'default' : 'surface'"
                            @click="selectPortion(option, index)"
                        >
                            {{ portionOptionLabel(option) }}
                        </Button>
                    </div>

                    <Input
                        v-model.number="activeFoodPortionQuantity"
                        type="number"
                        min="0.1"
                        step="0.1"
                        class="py-2.5 text-lg"
                        @input="selectedPortionKey = ''"
                    />
                    <Select v-model="activeFoodPortionUnit">
                        <SelectTrigger class="py-2.5 text-lg">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="activeFoodUnit">{{ activeFoodUnit }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="rounded-xl border border-border bg-muted p-3">
                    <p class="text-base font-semibold">When did you have it?</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <Button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 px-3 text-base"
                            :variant="selectedMealType === mealType ? 'default' : 'inverse'"
                            @click="setMealType(mealType)"
                        >
                            {{ mealLabels[mealType] }}
                        </Button>
                    </div>
                </div>

                <MacroSummary :macros="activeFoodMacros" />

                <Button
                    class="w-full text-lg"
                    size="lg"
                    :disabled="barcodeMealForm.processing"
                    @click="selectedPreviousMeal ? addPreviousMeal() : addBarcodeMeal()"
                >
                    <Plus :size="18" />
                    Add {{ activeFoodCalories }} kcal
                </Button>
            </div>
        </AppSheet>

        <Card v-if="mode === 'custom' || analysisContext">
            <div class="flex items-center gap-2">
                <Utensils :size="21" class="text-food" />
                <h2 class="font-semibold">{{ analysisContext ? 'Review meal estimate' : 'Custom food' }}</h2>
            </div>

            <div v-if="analysisContext" class="mt-4 rounded-xl bg-muted p-3 text-sm">
                <p><strong>Confidence:</strong> {{ Math.round(analysisContext.confidence * 100) }}%</p>
                <p v-if="analysisContext.recognized_components.length" class="mt-1 text-muted-foreground">
                    Recognized: {{ analysisContext.recognized_components.join(', ') }}
                </p>
                <p class="mt-2 text-muted-foreground">Review every value before saving.</p>
            </div>

            <Button v-if="analysisContext" type="button" variant="surface" class="mt-4 w-full" @click="openFollowUpModal">
                <Pencil :size="18" />
                Follow-up
            </Button>

            <div v-if="previousCustomMeals.length && !analysisContext" class="mt-4">
                <p class="field-label">Previous custom foods</p>
                <div class="mt-2 grid gap-2">
                    <Button
                        v-for="meal in previousCustomMeals"
                        :key="meal.id"
                        type="button"
                        variant="surface"
                        class="h-auto justify-start p-3 text-left"
                        @click="selectPreviousCustomMeal(meal)"
                    >
                        <span class="block font-semibold">{{ meal.name }}</span>
                        <span class="block text-sm text-muted-foreground">
                            {{ meal.calories }} kcal<span v-if="meal.portion_quantity"> · {{ meal.portion_quantity }}{{ meal.portion_unit }}</span> · P {{ meal.protein_g }}g · C {{ meal.carbs_g }}g · F {{ meal.fat_g }}g
                        </span>
                    </Button>
                </div>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addCustomMeal">
                <label class="block">
                    <span class="field-label">Name</span>
                    <Input
                        v-model="customMealForm.name"
                        type="text"
                        class="mt-1"
                    />
                    <span v-if="customMealForm.errors.name" class="mt-1 block text-sm text-destructive">{{ customMealForm.errors.name }}</span>
                </label>

                <div class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                    <label>
                        <span class="field-label">Portion</span>
                        <Input
                            v-model.number="customMealForm.portion_quantity"
                            type="number"
                            min="0.1"
                            step="0.1"
                            class="mt-1 text-right font-semibold"
                        />
                        <span v-if="customMealForm.errors.portion_quantity" class="mt-1 block text-sm text-destructive">{{ customMealForm.errors.portion_quantity }}</span>
                    </label>

                    <label>
                        <span class="field-label">Unit</span>
                        <Select v-model="customMealForm.portion_unit" class="mt-1">
                            <SelectTrigger class="px-2 font-semibold">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="g">g</SelectItem>
                                <SelectItem value="ml">ml</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="customMealForm.errors.portion_unit" class="mt-1 block text-sm text-destructive">{{ customMealForm.errors.portion_unit }}</span>
                    </label>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <label v-for="field in customMealMacroFields" :key="field[0]">
                        <span class="field-label">{{ field[1] }}</span>
                        <Input
                            v-model.number="customMealForm[field[0]]"
                            type="number"
                            min="0"
                            step="0.1"
                            placeholder="0"
                            class="mt-1 px-2 text-right font-semibold"
                        />
                    </label>
                </div>

                <div class="rounded-xl border border-border bg-muted p-3">
                    <p class="text-sm font-semibold">When did you have it?</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <Button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 px-3 text-sm"
                            :variant="selectedMealType === mealType ? 'default' : 'inverse'"
                            @click="setMealType(mealType)"
                        >
                            {{ mealLabels[mealType] }}
                        </Button>
                    </div>
                </div>

                <div :class="analysisContext ? 'grid grid-cols-2 gap-2' : ''">
                    <Button v-if="analysisContext" type="button" variant="surface" :disabled="customMealForm.processing" @click="cancelAnalysis">
                        Cancel
                    </Button>
                    <Button class="w-full" :disabled="customMealForm.processing">
                        {{ analysisContext ? 'Save' : 'Add' }} {{ customCalories }} kcal
                    </Button>
                </div>
            </form>
        </Card>

        <AppSheet :open="analysisFollowUpOpen" labelled-by="analysis-follow-up-title" @close="closeFollowUpModal">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="analysis-follow-up-title" class="text-xl font-semibold">Follow up on estimate</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Tell Buff what it got wrong. You can add another follow-up after each update.</p>
                </div>
                <Button type="button" variant="ghost" size="icon" aria-label="Close follow-up" :disabled="analysisFollowUpLoading" @click="closeFollowUpModal">
                    <X :size="20" />
                </Button>
            </div>

            <form class="mt-4 space-y-3" @submit.prevent="followUpAnalysis">
                <label for="analysis-follow-up" class="block field-label">Correction</label>
                <Textarea
                    id="analysis-follow-up"
                    v-model="analysisFollowUp"
                    rows="3"
                    maxlength="1000"
                    autofocus
                    placeholder="It was blue cheese, not feta…"
                    :disabled="analysisFollowUpLoading"
                />
                <p v-if="analysisFollowUpError" class="text-sm text-destructive" role="alert">{{ analysisFollowUpError }}</p>
                <Button class="w-full" :disabled="analysisFollowUpLoading || !analysisFollowUp.trim()">
                    <LoaderCircle v-if="analysisFollowUpLoading" :size="18" class="animate-spin" />
                    {{ analysisFollowUpLoading ? 'Updating estimate…' : 'Update estimate' }}
                </Button>
            </form>
        </AppSheet>

        <Card v-if="mode === 'workout'">
            <div class="flex items-center gap-2">
                <Dumbbell :size="21" class="text-workout" />
                <h2 class="font-semibold">Workout</h2>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addWorkout">
                <label class="block">
                    <span class="field-label">Title</span>
                    <Input
                        v-model="workoutForm.title"
                        type="text"
                        class="mt-1"
                    />
                    <span v-if="workoutForm.errors.title" class="mt-1 block text-sm text-destructive">{{ workoutForm.errors.title }}</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <label>
                        <span class="field-label">Calories burnt</span>
                        <Input
                            v-model.number="workoutForm.calories_burned"
                            type="number"
                            min="1"
                            step="1"
                            placeholder="0"
                            class="mt-1 text-right font-semibold"
                        />
                        <span v-if="workoutForm.errors.calories_burned" class="mt-1 block text-sm text-destructive">{{ workoutForm.errors.calories_burned }}</span>
                    </label>

                    <label>
                        <span class="field-label">Time</span>
                        <Input
                            v-model="workoutForm.time"
                            type="time"
                            class="mt-1 font-semibold"
                        />
                        <span v-if="workoutForm.errors.time" class="mt-1 block text-sm text-destructive">{{ workoutForm.errors.time }}</span>
                    </label>
                </div>

                <Button class="w-full" :disabled="workoutForm.processing">
                    <Plus :size="18" />
                    Add workout
                </Button>
            </form>
        </Card>
    </section>
</template>
