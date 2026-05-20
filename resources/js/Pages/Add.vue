<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Barcode, Camera, Dumbbell, LoaderCircle, Plus, Search, Utensils, History, X } from '@lucide/vue';
import { formatDisplayDate } from '../dateFormat';
import Card from "../Components/Card.vue";

const props = defineProps({
    date: { type: String, required: true },
    mealTypes: { type: Array, required: true },
    mode: { type: String, required: true },
    autoScan: { type: Boolean, default: false },
    previousFoodEntries: { type: Array, default: () => [] },
    previousCustomMeals: { type: Array, default: () => [] },
    previousBreakfastMeals: { type: Array, default: () => [] },
});

const mealLabels = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

function currentTime() {
    return new Date().toTimeString().slice(0, 5);
}

function addModeUrl(mode, extra = {}) {
    const params = new URLSearchParams({
        date: props.date,
        mode,
        ...extra,
    });

    return `/add?${params.toString()}`;
}

function smartMealType() {
    const hour = new Date().getHours();

    if (hour < 10) return 'breakfast';
    if (hour < 14) return 'lunch';
    if (hour < 20) return 'dinner';

    return 'snacks';
}

const selectedMealType = ref(smartMealType());
const barcode = ref('');
const lookupError = ref('');
const lookupLoading = ref(false);
const nativeMessage = ref('');
const nativeBridge = ref(null);
const scannerStarting = ref(false);
const webScannerOpen = ref(false);
const webScannerVideo = ref(null);
const webScannerControls = ref(null);
const product = ref(null);
const portionOptions = ref([]);
const selectedPortionKey = ref('');
const manualBarcodeOpen = ref(false);
const foodSearch = ref('');
const foodSearchLoading = ref(false);
const foodSearchResults = ref([]);
const selectedPreviousMeal = ref(null);
const previousMealPortionQuantity = ref(null);
const previousMealPortionUnit = ref('g');
let foodSearchRequestId = 0;

const customMealForm = useForm({
    date: props.date,
    meal_type: selectedMealType.value,
    name: '',
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

const displayDate = computed(() => formatDisplayDate(props.date));
const foodSearchQuery = computed(() => foodSearch.value.trim());
const foodAddSheetOpen = computed(() => Boolean(product.value || selectedPreviousMeal.value));

function setMealType(mealType) {
    selectedMealType.value = mealType;
    customMealForm.meal_type = mealType;
    barcodeMealForm.meal_type = mealType;
}

function selectPortion(option, index) {
    selectedPortionKey.value = String(index);
    barcodeMealForm.portion_quantity = option.quantity;
    barcodeMealForm.portion_unit = option.unit;
}

async function lookup(scannedBarcode = null) {
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
        buzz();

        if (portionOptions.value.length > 0) {
            selectPortion(portionOptions.value[0], 0);
        }
    } catch (error) {
        product.value = null;
        const errors = error.response?.data?.errors;
        lookupError.value = errors?.barcode?.[0] || 'Could not look up that barcode. Add it as a custom meal instead.';
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

function selectFoodProduct(foodProduct) {
    selectedPreviousMeal.value = null;
    product.value = foodProduct;
    portionOptions.value = [
        { label: `100${foodProduct.nutrition_unit || 'g'}`, quantity: 100, unit: foodProduct.nutrition_unit || 'g' },
    ];
    barcode.value = foodProduct.barcode;
    barcodeMealForm.food_product_id = foodProduct.id;
    barcodeMealForm.portion_unit = foodProduct.nutrition_unit || 'g';
    selectPortion(portionOptions.value[0], 0);
    buzz();
}

function selectPreviousMeal(meal) {
    selectedPreviousMeal.value = meal;
    product.value = null;
    previousMealPortionQuantity.value = meal.portion_quantity;
    previousMealPortionUnit.value = meal.portion_unit || 'g';
    buzz();
}

function selectFoodResult(result) {
    if (result.type === 'previous_meal') {
        selectPreviousMeal(result);
        return;
    }

    selectFoodProduct(result);
}

async function buzz() {
    if (navigator.vibrate) {
        navigator.vibrate(35);
    }

    try {
        const native = nativeBridge.value || await import('#nativephp');
        await native.Haptics?.impact?.('medium');
    } catch {
        // Browser vibration is enough when the native haptics bridge is unavailable.
    }
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

function cameraErrorMessage(error) {
    if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
        return 'Camera permission was denied. Allow camera access for Buff, or enter the barcode manually.';
    }

    if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
        return 'No usable camera was found. Enter the barcode manually.';
    }

    return 'Scanner could not start. Enter the barcode manually.';
}

function handleScan(payload) {
    if (payload?.id && payload.id !== 'product-scanner') return;

    const scanned = payload?.data || payload;

    if (scanned) {
        barcode.value = scanned;
        lookup(scanned);
    }
}

function addBarcodeMeal() {
    barcodeMealForm.meal_type = selectedMealType.value;
    barcodeMealForm.post('/meals/barcode');
}

function addPreviousMeal() {
    if (!selectedPreviousMeal.value) return;

    const payload = {
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
}

function addCustomMeal() {
    customMealForm.meal_type = selectedMealType.value;
    customMealForm.post('/meals/custom');
}

function addWorkout() {
    workoutForm.post('/workouts');
}

function selectPreviousCustomMeal(meal) {
    customMealForm.name = meal.name;
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
                <p class="text-sm  text-stone-500">{{ displayDate }}</p>
                <h1 class="text-3xl font-semibold tracking-normal text-[#17211b]">
                    {{ mode === 'food' ? 'Add food' : mode === 'custom' ? 'Custom meal' : mode === 'workout' ? 'Workout' : 'Add' }}
                </h1>
            </div>
        </header>

        <div v-if="webScannerOpen" class="fixed inset-0 z-50 flex flex-col bg-[#17211b] text-white">
            <div class="flex items-center justify-between gap-3 px-4 py-3 pt-[calc(env(safe-area-inset-top,0px)+0.75rem)]">
                <div class="min-w-0">
                    <p class="text-sm  text-white/70">{{ manualBarcodeOpen ? 'Manual barcode' : 'Scan barcode' }}</p>
                    <h2 class="truncate text-xl font-semibold">{{ scannerStarting ? 'Opening camera...' : manualBarcodeOpen ? 'Enter barcode' : 'Point camera at barcode' }}</h2>
                </div>
                <button class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-white/10 active:bg-white/15" aria-label="Close scanner" @click="stopWebScan">
                    <X :size="22" />
                </button>
            </div>

            <div class="relative min-h-0 flex-1">
                <video v-show="!manualBarcodeOpen" ref="webScannerVideo" class="h-full w-full bg-black object-cover" muted playsinline />

                <div v-if="manualBarcodeOpen" class="flex h-full items-center px-4">
                    <div class="w-full rounded-md bg-white p-4 text-[#17211b]">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-stone-500">Barcode</span>
                            <input
                                v-model="barcode"
                                type="text"
                                inputmode="numeric"
                                placeholder="Enter barcode"
                                class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-base  outline-none focus:border-[#6f9b58]"
                            >
                        </label>
                        <button class="mt-3 flex w-full items-center justify-center gap-2 rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="lookupLoading" @click="lookup(); stopWebScan()">
                            <Search :size="19" />
                            Look up barcode
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-2 px-4 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-3">
                <button v-if="!manualBarcodeOpen" class="w-full rounded-md bg-white px-4 py-3 font-semibold text-[#17211b] active:bg-stone-100" @click="showManualBarcodeInput">
                    Enter barcode manually
                </button>
            </div>
        </div>

        <article v-if="mode === 'choose'" class="grid gap-3">
            <Link :href="addModeUrl('food')" class="flex items-center gap-3 rounded-md border border-stone-200 bg-white p-4 active:bg-stone-50">
                <span class="grid h-11 w-11 place-items-center rounded-md bg-[#253d2c] text-white">
                    <Utensils :size="22" />
                </span>
                <span>
                    <span class="block font-semibold">Food</span>
                    <span class="block text-sm font-medium text-stone-500">Search or scan</span>
                </span>
            </Link>

            <Link :href="addModeUrl('workout')" class="flex items-center gap-3 rounded-md border border-stone-200 bg-white p-4 active:bg-stone-50">
                <span class="grid h-11 w-11 place-items-center rounded-md bg-[#6f9b58] text-white">
                    <Dumbbell :size="22" />
                </span>
                <span>
                    <span class="block font-semibold">Workout</span>
                    <span class="block text-sm font-medium text-stone-500">Log calories burned</span>
                </span>
            </Link>
        </article>

        <Card v-if="mode === 'food'">
            <div class="flex items-center gap-2">
                <Search :size="21" class="text-[#b05252]" />
                <h2 class="font-semibold">Food</h2>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="searchFoodProducts">
                <input
                    v-model="foodSearch"
                    type="search"
                    placeholder="Search..."
                    class="min-w-0 flex-1 rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-base  outline-none focus:border-[#6f9b58]"
                    @input="searchFoodProducts"
                >
                <button class="grid aspect-square h-[50px] shrink-0 place-items-center rounded-md bg-[#253d2c] text-white active:bg-[#17211b] disabled:opacity-80" :disabled="foodSearchLoading" aria-label="Search">
                    <LoaderCircle v-if="foodSearchLoading" :size="21" class="animate-spin" />
                    <Search v-else :size="21" />
                </button>
            </form>

            <button class="mt-3 flex w-full items-center justify-center gap-2 rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="scannerStarting" @click="startScan">
                <Camera :size="20" />
                {{ scannerStarting ? 'Opening scanner...' : 'Scan barcode' }}
            </button>

            <p v-if="nativeMessage" class="mt-3 rounded-md bg-stone-100 p-3 text-sm  text-stone-700">{{ nativeMessage }}</p>
            <p v-if="lookupError" class="mt-3 rounded-md bg-red-50 p-3 text-sm  text-red-800">{{ lookupError }}</p>

            <div v-if="foodSearchLoading" class="mt-4 flex items-center gap-2 rounded-md bg-stone-100 p-3 text-sm  text-stone-600" role="status" aria-live="polite">
                <LoaderCircle :size="17" class="animate-spin text-[#253d2c]" />
                Searching...
            </div>

            <div v-else-if="foodSearchResults.length" class="mt-4 grid gap-2">
                <button
                    v-for="result in foodSearchResults"
                    :key="result.id"
                    type="button"
                    class="flex w-full min-w-0 items-center gap-3 overflow-hidden rounded-md border border-stone-200 bg-stone-50 p-3 text-left active:bg-stone-100"
                    @click="selectFoodResult(result)"
                >
                    <img v-if="result.image_url" :src="result.image_url" alt="" class="h-12 w-12 shrink-0 rounded-md object-cover">
                    <span v-else class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-stone-200 text-stone-500">
                        <Utensils v-if="result.type === 'previous_meal'" :size="20" />
                        <Barcode v-else :size="20" />
                    </span>
                    <span class="min-w-0 flex-1 flex items-center gap-2 overflow-hidden">
                        <History v-if="result.type === 'previous_meal'" :size="20" />
                        <span class="block truncate font-semibold">
                            {{ result.name }}
                        </span>
                    </span>
                </button>
            </div>

            <div v-else-if="!foodSearchQuery && previousFoodEntries.length" class="mt-4">
                <p class="text-xs font-semibold uppercase text-stone-500">Previous food entries</p>
                <div class="mt-2 grid gap-2">
                    <button
                        v-for="entry in previousFoodEntries"
                        :key="entry.id"
                        type="button"
                        class="flex w-full min-w-0 items-center gap-3 overflow-hidden rounded-md border border-stone-200 bg-stone-50 p-3 text-left active:bg-stone-100"
                        @click="selectPreviousMeal(entry)"
                    >
                        <img v-if="entry.image_url" :src="entry.image_url" alt="" class="h-12 w-12 shrink-0 rounded-md object-cover">
                        <span v-else class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-stone-200 text-stone-500">
                            <Utensils :size="20" />
                        </span>
                        <span class="min-w-0 flex-1 overflow-hidden">
                            <span class="block truncate font-semibold">{{ entry.name }}</span>
                            <span class="block truncate text-sm text-stone-500">
                                <span v-if="entry.portion_quantity">{{ entry.portion_quantity }}{{ entry.portion_unit }}</span>
                            </span>
                        </span>
                    </button>
                </div>
            </div>

            <p v-else-if="foodSearchQuery.length >= 2 && !foodSearchLoading" class="mt-4 rounded-md bg-stone-100 p-3 text-sm  text-stone-600">
                No products found.
            </p>
        </Card>

        <div
            v-if="foodAddSheetOpen"
            class="fixed inset-0 z-40 bg-black/30"
            @click="closeFoodAddSheet"
        />

        <section
            v-if="foodAddSheetOpen"
            class="fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[88vh] max-w-md overflow-y-auto rounded-t-lg bg-white px-4 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-3 shadow-[0_-18px_50px_rgba(23,33,27,0.22)] transition-transform duration-200"
            :class="foodAddSheetOpen ? 'translate-y-0' : 'translate-y-full'"
            :aria-hidden="!foodAddSheetOpen"
            :inert="!foodAddSheetOpen"
            aria-label="Add food"
        >
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-stone-300" />
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm  text-stone-500">{{ selectedPreviousMeal ? 'Previous food' : 'Food product' }}</p>
                    <h2 class="truncate text-xl font-semibold text-[#17211b]">{{ selectedPreviousMeal?.name || product?.name || 'Add food' }}</h2>
                </div>
                <button class="grid h-10 w-10 shrink-0 place-items-center rounded-md text-stone-500 active:bg-stone-100" aria-label="Close add food" @click="closeFoodAddSheet">
                    <X :size="21" />
                </button>
            </div>

            <div v-if="selectedPreviousMeal" class="space-y-5">
                <div class="flex gap-4">
                    <img v-if="selectedPreviousMeal.image_url" :src="selectedPreviousMeal.image_url" alt="" class="h-20 w-20 rounded-md object-cover">
                    <span v-else class="grid h-20 w-20 shrink-0 place-items-center rounded-md bg-stone-200 text-stone-500">
                        <Utensils :size="26" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ selectedPreviousMeal.name }}</p>
                        <p class="truncate text-sm text-stone-500">{{ selectedPreviousMeal.brand || 'Previous item' }}</p>
                        <p class="mt-1 text-sm ">
                            {{ selectedPreviousMeal.calories }} kcal<span v-if="selectedPreviousMeal.portion_quantity"> · {{ selectedPreviousMeal.portion_quantity }}{{ selectedPreviousMeal.portion_unit }}</span> · P {{ selectedPreviousMeal.protein_g }}g · C {{ selectedPreviousMeal.carbs_g }}g · F {{ selectedPreviousMeal.fat_g }}g
                        </p>
                    </div>
                </div>

                <div v-if="previousMealHasPortion" class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                    <input
                        v-model.number="previousMealPortionQuantity"
                        type="number"
                        min="0.1"
                        step="0.1"
                        class="rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                    >
                    <select
                        v-model="previousMealPortionUnit"
                        class="w-full rounded-md border border-stone-200 bg-stone-50 px-2 py-3  outline-none focus:border-[#6f9b58]"
                    >
                        <option :value="previousMealPortionUnit">{{ previousMealPortionUnit }}</option>
                    </select>
                </div>

                <div class="rounded-md border border-stone-200 bg-stone-50 p-3">
                    <p class="text-sm font-semibold">When did you have it?</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 rounded px-3 text-sm font-semibold transition"
                            :class="selectedMealType === mealType ? 'bg-[#253d2c] text-white' : 'bg-white text-stone-600 active:bg-stone-100'"
                            @click="setMealType(mealType)"
                        >
                            {{ mealLabels[mealType] }}
                        </button>
                    </div>
                </div>

                <button class="flex w-full items-center justify-center gap-2 rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" @click="addPreviousMeal">
                    <Plus :size="18" />
                    Add {{ previousMealCalories }} kcal
                </button>
            </div>

            <div v-if="product" class="space-y-5">
                <div class="flex gap-4">
                    <img v-if="product.image_url" :src="product.image_url" alt="" class="h-20 w-20 rounded-md object-cover">
                    <span v-else class="grid h-20 w-20 shrink-0 place-items-center rounded-md bg-stone-200 text-stone-500">
                        <Barcode :size="26" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ product.name }}</p>
                        <p class="truncate text-sm text-stone-500">{{ product.brand || 'Saved product' }}</p>
                        <p class="mt-1 text-sm ">
                            {{ product.calories_per_100 }} kcal · P {{ product.protein_per_100 }}g · C {{ product.carbs_per_100 }}g · F {{ product.fat_per_100 }}g / 100{{ product.nutrition_unit }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                    <input
                        v-model.number="barcodeMealForm.portion_quantity"
                        type="number"
                        min="0.1"
                        step="0.1"
                        class="rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                    >
                    <select
                        v-model="barcodeMealForm.portion_unit"
                        class="w-full rounded-md border border-stone-200 bg-stone-50 px-2 py-3  outline-none focus:border-[#6f9b58]"
                    >
                        <option value="g">g</option>
                        <option value="ml">ml</option>
                    </select>
                </div>

                <div class="rounded-md border border-stone-200 bg-stone-50 p-3">
                    <p class="text-sm font-semibold">When did you have it?</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 rounded px-3 text-sm font-semibold transition"
                            :class="selectedMealType === mealType ? 'bg-[#253d2c] text-white' : 'bg-white text-stone-600 active:bg-stone-100'"
                            @click="setMealType(mealType)"
                        >
                            {{ mealLabels[mealType] }}
                        </button>
                    </div>
                </div>

                <button class="flex w-full items-center justify-center gap-2 rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="barcodeMealForm.processing" @click="addBarcodeMeal">
                    <Plus :size="18" />
                    Add {{ barcodeCalories }} kcal
                </button>
            </div>
        </section>

        <Card v-if="mode === 'custom'">
            <div class="flex items-center gap-2">
                <Utensils :size="21" class="text-[#d28a45]" />
                <h2 class="font-semibold">Custom meal</h2>
            </div>

            <div v-if="previousCustomMeals.length" class="mt-4">
                <p class="text-xs font-semibold uppercase text-stone-500">Previous custom meals</p>
                <div class="mt-2 grid gap-2">
                    <button
                        v-for="meal in previousCustomMeals"
                        :key="meal.id"
                        type="button"
                        class="rounded-md border border-stone-200 bg-stone-50 p-3 text-left active:bg-stone-100"
                        @click="selectPreviousCustomMeal(meal)"
                    >
                        <span class="block font-semibold">{{ meal.name }}</span>
                        <span class="block text-sm  text-stone-500">
                            {{ meal.calories }} kcal · P {{ meal.protein_g }}g · C {{ meal.carbs_g }}g · F {{ meal.fat_g }}g
                        </span>
                    </button>
                </div>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addCustomMeal">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-stone-500">Name</span>
                    <input
                        v-model="customMealForm.name"
                        type="text"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="customMealForm.errors.name" class="mt-1 block text-sm  text-red-700">{{ customMealForm.errors.name }}</span>
                </label>

                <div class="grid grid-cols-3 gap-2">
                    <label v-for="field in [
                        ['protein_g', 'Protein'],
                        ['carbs_g', 'Carbs'],
                        ['fat_g', 'Fat'],
                    ]" :key="field[0]">
                        <span class="text-xs font-semibold uppercase text-stone-500">{{ field[1] }}</span>
                        <input
                            v-model.number="customMealForm[field[0]]"
                            type="number"
                            min="0"
                            step="0.1"
                            class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-2 py-3 text-right font-semibold outline-none focus:border-[#6f9b58]"
                        >
                    </label>
                </div>

                <div class="rounded-md border border-stone-200 bg-stone-50 p-3">
                    <p class="text-sm font-semibold">When did you have it?</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 rounded px-3 text-sm font-semibold transition"
                            :class="selectedMealType === mealType ? 'bg-[#253d2c] text-white' : 'bg-white text-stone-600 active:bg-stone-100'"
                            @click="setMealType(mealType)"
                        >
                            {{ mealLabels[mealType] }}
                        </button>
                    </div>
                </div>

                <button class="w-full rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="customMealForm.processing">
                    Add {{ customCalories }} kcal
                </button>
            </form>
        </Card>

        <Card v-if="mode === 'workout'">
            <div class="flex items-center gap-2">
                <Dumbbell :size="21" class="text-[#6f9b58]" />
                <h2 class="font-semibold">Workout</h2>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addWorkout">
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-stone-500">Title</span>
                    <input
                        v-model="workoutForm.title"
                        type="text"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="workoutForm.errors.title" class="mt-1 block text-sm  text-red-700">{{ workoutForm.errors.title }}</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <label>
                        <span class="text-xs font-semibold uppercase text-stone-500">Calories burnt</span>
                        <input
                            v-model.number="workoutForm.calories_burned"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-right font-semibold outline-none focus:border-[#6f9b58]"
                        >
                        <span v-if="workoutForm.errors.calories_burned" class="mt-1 block text-sm  text-red-700">{{ workoutForm.errors.calories_burned }}</span>
                    </label>

                    <label>
                        <span class="text-xs font-semibold uppercase text-stone-500">Time</span>
                        <input
                            v-model="workoutForm.time"
                            type="time"
                            class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                        >
                        <span v-if="workoutForm.errors.time" class="mt-1 block text-sm  text-red-700">{{ workoutForm.errors.time }}</span>
                    </label>
                </div>

                <button class="flex w-full items-center justify-center gap-2 rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="workoutForm.processing">
                    <Plus :size="18" />
                    Add workout
                </button>
            </form>
        </Card>
    </section>
</template>
