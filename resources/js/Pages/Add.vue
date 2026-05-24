<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Barcode, Camera, Pencil, Dumbbell, LoaderCircle, Plus, Search, Utensils, History, X } from '@lucide/vue';
import { formatDisplayDate } from '../dateFormat';
import { hapticImpact } from '../haptics';
import Card from "../Components/Card.vue";
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';

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
    id: number;
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

const customMealForm = useForm({
    date: props.date,
    meal_type: selectedMealType.value,
    name: '',
    portion_quantity: 100,
    portion_unit: 'g',
    protein_g: 0,
    carbs_g: 0,
    fat_g: 0,
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
    calories_burned: 0,
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
    manualBarcodeOpen.value = true;
    webScannerOpen.value = true;
    scannerStarting.value = false;
}

async function startWebScan() {
    stopWebScan();
    nativeMessage.value = '';
    scannerStarting.value = true;
    webScannerOpen.value = true;

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
    webScannerOpen.value = false;
    manualBarcodeOpen.value = false;
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
    customMealForm.post('/meals/custom');
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

    if (nativeBridge.value) {
        nativeBridge.value.Off(nativeBridge.value.Events.Scanner.CodeScanned, handleScan);
    }
});
</script>

<template>
    <Head title="Add" />

    <section class="space-y-5">
        <header>
            <div>
                <p class="text-sm  text-muted-foreground">{{ displayDate }}</p>
                <h1 class="text-3xl font-semibold tracking-normal text-foreground">
                    {{ mode === 'food' ? 'Add food' : mode === 'custom' ? 'Custom food' : mode === 'workout' ? 'Workout' : 'Add' }}
                    <span v-if="meal">
                         - {{ meal }}
                    </span>
                </h1>
            </div>
        </header>

        <div v-if="webScannerOpen" class="fixed inset-0 z-50 flex flex-col bg-foreground text-primary-foreground">
            <div class="flex items-center justify-between gap-3 px-4 py-3 pt-[calc(env(safe-area-inset-top,0px)+0.75rem)]">
                <div class="min-w-0">
                    <p class="text-sm  text-primary-foreground/70">{{ manualBarcodeOpen ? 'Manual barcode' : 'Scan barcode' }}</p>
                    <h2 class="truncate text-xl font-semibold">{{ scannerStarting ? 'Opening camera...' : manualBarcodeOpen ? 'Enter barcode' : 'Point camera at barcode' }}</h2>
                </div>
                <Button variant="ghost" size="icon" class="h-11 w-11 shrink-0 bg-primary-foreground/10 text-primary-foreground active:bg-primary-foreground/15" aria-label="Close scanner" @click="stopWebScan">
                    <X :size="22" />
                </Button>
            </div>

            <div class="relative min-h-0 flex-1">
                <video v-show="!manualBarcodeOpen" ref="webScannerVideo" class="h-full w-full bg-foreground object-cover" muted playsinline />

                <div v-if="manualBarcodeOpen" class="flex h-full items-center px-4">
                    <div class="w-full rounded-md bg-card p-4 text-foreground">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Barcode</span>
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
            <Button :as="Link" :href="addModeUrl('food')" variant="outline" class="h-auto justify-start p-4 text-left">
                <span class="grid h-11 w-11 place-items-center rounded-md bg-primary text-primary-foreground">
                    <Utensils :size="22" />
                </span>
                <span>
                    <span class="block font-semibold">Food</span>
                    <span class="block text-sm font-medium text-muted-foreground">Search or scan</span>
                </span>
            </Button>

            <Button :as="Link" :href="addModeUrl('workout')" variant="outline" class="h-auto justify-start p-4 text-left">
                <span class="grid h-11 w-11 place-items-center rounded-md bg-workout text-primary-foreground">
                    <Dumbbell :size="22" />
                </span>
                <span>
                    <span class="block font-semibold">Workout</span>
                    <span class="block text-sm font-medium text-muted-foreground">Log calories burned</span>
                </span>
            </Button>
        </article>

        <Card v-if="mode === 'food'">
            <div class="flex items-center gap-2">
                <Search :size="21" class="text-fat" />
                <h2 class="font-semibold">Food</h2>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="searchFoodProducts">
                <Input
                    v-model="foodSearch"
                    type="search"
                    placeholder="Search..."
                    class="min-w-0 flex-1"
                    @input="searchFoodProducts"
                />
                <Button class="aspect-square h-[50px] shrink-0 px-0 py-0" :disabled="foodSearchLoading" aria-label="Search">
                    <LoaderCircle v-if="foodSearchLoading" :size="21" class="animate-spin" />
                    <Search v-else :size="21" />
                </Button>
            </form>

            <div class="flex gap-2">
            <Button class="mt-3 w-full" :disabled="scannerStarting" @click="startScan">
                <Camera :size="20" />
                {{ scannerStarting ? 'Opening...' : 'Scan' }}
            </Button>
            <Button
                :as="Link"
                :href="`/add?date=${date}&mode=custom`"
                class="mt-3 w-full">
                <Pencil :size="20" />
                Custom
            </Button>
            </div>

            <p v-if="nativeMessage" class="mt-3 rounded-md bg-muted p-3 text-sm  text-foreground/80">{{ nativeMessage }}</p>
            <p v-if="lookupError" class="mt-3 rounded-md bg-danger-soft p-3 text-sm  text-danger-soft-foreground">{{ lookupError }}</p>

            <div v-if="foodSearchLoading" class="mt-4 flex items-center gap-2 rounded-md bg-muted p-3 text-sm  text-muted-foreground" role="status" aria-live="polite">
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
                    <img v-if="result.image_url" :src="result.image_url" alt="" class="h-12 w-12 shrink-0 rounded-md object-cover">
                    <span v-else class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground">
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
                <p class="text-xs font-semibold uppercase text-muted-foreground">Previous food entries</p>
                <div class="mt-2 grid gap-2">
                    <Button
                        v-for="entry in previousFoodEntries"
                        :key="entry.id"
                        type="button"
                        variant="surface"
                        class="h-auto w-full min-w-0 justify-start overflow-hidden p-3 text-left"
                        @click="selectPreviousMeal(entry)"
                    >
                        <img v-if="entry.image_url" :src="entry.image_url" alt="" class="h-12 w-12 shrink-0 rounded-md object-cover">
                        <span v-else class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground">
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

            <p v-else-if="foodSearchQuery.length >= 2 && !foodSearchLoading" class="mt-4 rounded-md bg-muted p-3 text-sm  text-muted-foreground">
                No products found.
            </p>
        </Card>

        <div
            v-if="foodAddSheetOpen"
            class="fixed inset-0 z-40 bg-foreground/30"
            @click="closeFoodAddSheet"
        />

        <section
            v-if="foodAddSheetOpen"
            class="fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[88vh] max-w-md overflow-y-auto rounded-t-lg bg-card px-6 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-6 shadow-2xl transition-transform duration-200"
            :class="foodAddSheetOpen ? 'translate-y-0' : 'translate-y-full'"
            :aria-hidden="!foodAddSheetOpen"
            :inert="!foodAddSheetOpen"
            aria-label="Add food"
        >
            <Button variant="ghost" size="icon" class="absolute right-4 top-4 h-10 w-10 shrink-0" aria-label="Close add food" @click="closeFoodAddSheet">
                <X :size="24" />
            </Button>

            <div v-if="selectedPreviousMeal || product" class="space-y-5">
                <div class="flex gap-4">
                    <img v-if="(selectedPreviousMeal || product)?.image_url" :src="(selectedPreviousMeal || product)?.image_url || ''" alt="" class="h-20 w-20 rounded-md object-cover">
                    <span v-else class="grid size-20 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground">
                        <Utensils v-if="selectedPreviousMeal" :size="26" />
                        <Barcode v-else :size="26" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-lg font-semibold text-foreground">{{ selectedPreviousMeal?.name || product?.name || 'Add food' }}</p>
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
                    <Select
                        v-model="activeFoodPortionUnit"
                        class="py-2.5 text-lg"
                    >
                        <option :value="activeFoodUnit">{{ activeFoodUnit }}</option>
                    </Select>
                </div>

                <div class="rounded-md border border-border bg-muted p-3">
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

                <div class="grid grid-cols-4 gap-2 rounded-md border border-border bg-muted p-3 text-center">
                    <div>
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Kcal</p>
                        <p class="mt-1 text-lg font-semibold text-foreground">{{ activeFoodMacros.calories }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Protein</p>
                        <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(activeFoodMacros.protein_g) }}g</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Carbs</p>
                        <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(activeFoodMacros.carbs_g) }}g</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-muted-foreground">Fat</p>
                        <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(activeFoodMacros.fat_g) }}g</p>
                    </div>
                </div>

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
        </section>

        <Card v-if="mode === 'custom'">
            <div class="flex items-center gap-2">
                <Utensils :size="21" class="text-food" />
                <h2 class="font-semibold">Custom food</h2>
            </div>

            <div v-if="previousCustomMeals.length" class="mt-4">
                <p class="text-xs font-semibold uppercase text-muted-foreground">Previous custom foods</p>
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
                        <span class="block text-sm  text-muted-foreground">
                            {{ meal.calories }} kcal<span v-if="meal.portion_quantity"> · {{ meal.portion_quantity }}{{ meal.portion_unit }}</span> · P {{ meal.protein_g }}g · C {{ meal.carbs_g }}g · F {{ meal.fat_g }}g
                        </span>
                    </Button>
                </div>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addCustomMeal">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Name</span>
                    <Input
                        v-model="customMealForm.name"
                        type="text"
                        class="mt-1"
                    />
                    <span v-if="customMealForm.errors.name" class="mt-1 block text-sm  text-destructive">{{ customMealForm.errors.name }}</span>
                </label>

                <div class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Portion</span>
                        <Input
                            v-model.number="customMealForm.portion_quantity"
                            type="number"
                            min="0.1"
                            step="0.1"
                            class="mt-1 text-right font-semibold"
                        />
                        <span v-if="customMealForm.errors.portion_quantity" class="mt-1 block text-sm  text-destructive">{{ customMealForm.errors.portion_quantity }}</span>
                    </label>

                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Unit</span>
                        <Select
                            v-model="customMealForm.portion_unit"
                            class="mt-1 px-2 font-semibold"
                        >
                            <option value="g">g</option>
                            <option value="ml">ml</option>
                        </Select>
                        <span v-if="customMealForm.errors.portion_unit" class="mt-1 block text-sm  text-destructive">{{ customMealForm.errors.portion_unit }}</span>
                    </label>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <label v-for="field in customMealMacroFields" :key="field[0]">
                        <span class="text-xs font-semibold uppercase text-muted-foreground">{{ field[1] }}</span>
                        <Input
                            v-model.number="customMealForm[field[0]]"
                            type="number"
                            min="0"
                            step="0.1"
                            class="mt-1 px-2 text-right font-semibold"
                        />
                    </label>
                </div>

                <div class="rounded-md border border-border bg-muted p-3">
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

                <Button class="w-full" :disabled="customMealForm.processing">
                    Add {{ customCalories }} kcal
                </Button>
            </form>
        </Card>

        <Card v-if="mode === 'workout'">
            <div class="flex items-center gap-2">
                <Dumbbell :size="21" class="text-workout" />
                <h2 class="font-semibold">Workout</h2>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addWorkout">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Title</span>
                    <Input
                        v-model="workoutForm.title"
                        type="text"
                        class="mt-1"
                    />
                    <span v-if="workoutForm.errors.title" class="mt-1 block text-sm  text-destructive">{{ workoutForm.errors.title }}</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Calories burnt</span>
                        <Input
                            v-model.number="workoutForm.calories_burned"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 text-right font-semibold"
                        />
                        <span v-if="workoutForm.errors.calories_burned" class="mt-1 block text-sm  text-destructive">{{ workoutForm.errors.calories_burned }}</span>
                    </label>

                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Time</span>
                        <Input
                            v-model="workoutForm.time"
                            type="time"
                            class="mt-1 font-semibold"
                        />
                        <span v-if="workoutForm.errors.time" class="mt-1 block text-sm  text-destructive">{{ workoutForm.errors.time }}</span>
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
