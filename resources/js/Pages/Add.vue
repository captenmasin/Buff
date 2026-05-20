<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Barcode, Camera, ChevronLeft, Dumbbell, Plus, Utensils, X } from '@lucide/vue';
import { formatDisplayDate } from '../dateFormat';
import Card from "../Components/Card.vue";

const props = defineProps({
    date: { type: String, required: true },
    mealTypes: { type: Array, required: true },
    mode: { type: String, required: true },
    autoScan: { type: Boolean, default: false },
    previousCustomMeals: { type: Array, default: () => [] },
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

const displayDate = computed(() => formatDisplayDate(props.date));

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

    try {
        const response = await axios.post('/barcode/lookup', {
            barcode: scannedBarcode || barcode.value,
        });

        product.value = response.data.product;
        portionOptions.value = response.data.portion_options || [];
        barcode.value = response.data.product.barcode;
        barcodeMealForm.food_product_id = response.data.product.id;
        barcodeMealForm.portion_unit = response.data.product.nutrition_unit || 'g';

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

async function startScan() {
    nativeMessage.value = '';
    scannerStarting.value = true;

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
        const [{ BrowserMultiFormatReader }, { BarcodeFormat, DecodeHintType }] = await Promise.all([
            import('@zxing/browser'),
            import('@zxing/library'),
        ]);
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
        const controls = await reader.decodeFromConstraints(
            {
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                },
            },
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

    if (props.mode === 'barcode' && props.autoScan) {
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
        <header class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-stone-500">{{ displayDate }}</p>
                <h1 class="text-3xl font-bold tracking-normal text-[#17211b]">
                    {{ mode === 'barcode' ? 'Scan food' : mode === 'custom' ? 'Custom meal' : mode === 'workout' ? 'Workout' : 'Add' }}
                </h1>
            </div>
            <Link href="/" class="rounded-md border border-stone-200 bg-white p-2 text-stone-600 active:bg-stone-100" aria-label="Back to today">
                <ChevronLeft :size="21" />
            </Link>
        </header>

        <article v-if="mode === 'choose'" class="grid gap-3">
            <Link :href="addModeUrl('barcode', { scan: '1' })" class="flex items-center gap-3 rounded-md border border-stone-200 bg-white p-4 active:bg-stone-50">
                <span class="grid h-11 w-11 place-items-center rounded-md bg-[#253d2c] text-white">
                    <Barcode :size="22" />
                </span>
                <span>
                    <span class="block font-bold">Barcode</span>
                    <span class="block text-sm font-medium text-stone-500">Open scanner</span>
                </span>
            </Link>

            <Link :href="addModeUrl('custom')" class="flex items-center gap-3 rounded-md border border-stone-200 bg-white p-4 active:bg-stone-50">
                <span class="grid h-11 w-11 place-items-center rounded-md bg-[#d28a45] text-white">
                    <Utensils :size="22" />
                </span>
                <span>
                    <span class="block font-bold">Custom meal</span>
                    <span class="block text-sm font-medium text-stone-500">Enter macros</span>
                </span>
            </Link>

            <Link :href="addModeUrl('workout')" class="flex items-center gap-3 rounded-md border border-stone-200 bg-white p-4 active:bg-stone-50">
                <span class="grid h-11 w-11 place-items-center rounded-md bg-[#6f9b58] text-white">
                    <Dumbbell :size="22" />
                </span>
                <span>
                    <span class="block font-bold">Workout</span>
                    <span class="block text-sm font-medium text-stone-500">Log calories burned</span>
                </span>
            </Link>
        </article>

        <Card v-if="mode === 'barcode'">
            <div class="flex items-center gap-2">
                <Barcode :size="21" class="text-[#253d2c]" />
                <h2 class="font-bold">Barcode</h2>
            </div>

            <div class="mt-4 flex gap-2">
                <input
                    v-model="barcode"
                    type="text"
                    inputmode="numeric"
                    placeholder="Barcode"
                    class="min-w-0 flex-1 rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-base font-semibold outline-none focus:border-[#6f9b58]"
                >
                <button class="rounded-md bg-[#253d2c] px-3 text-white active:bg-[#17211b]" aria-label="Scan barcode" @click="startScan">
                    <Camera :size="21" />
                </button>
            </div>

            <button class="mt-3 w-full rounded-md border border-stone-300 px-4 py-3 text-sm font-bold active:bg-stone-100" :disabled="lookupLoading" @click="lookup()">
                {{ lookupLoading ? 'Looking up...' : 'Look up barcode' }}
            </button>

            <div v-if="webScannerOpen" class="mt-3 overflow-hidden rounded-md border border-stone-200 bg-[#17211b]">
                <div class="flex items-center justify-between gap-3 px-3 py-2 text-white">
                    <span class="text-sm font-bold">{{ scannerStarting ? 'Opening camera...' : 'Point camera at barcode' }}</span>
                    <button class="rounded-md p-2 active:bg-white/10" aria-label="Close scanner" @click="stopWebScan">
                        <X :size="18" />
                    </button>
                </div>
                <video ref="webScannerVideo" class="aspect-[4/3] w-full bg-black object-cover" muted playsinline />
            </div>

            <p v-if="nativeMessage" class="mt-3 rounded-md bg-stone-100 p-3 text-sm font-semibold text-stone-700">{{ nativeMessage }}</p>
            <p v-if="lookupError" class="mt-3 rounded-md bg-red-50 p-3 text-sm font-semibold text-red-800">{{ lookupError }}</p>

            <div v-if="product" class="mt-5 space-y-5 rounded-md border border-stone-200 bg-stone-50 p-4">
                <div class="flex gap-4">
                    <img v-if="product.image_url" :src="product.image_url" alt="" class="h-20 w-20 rounded-md object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold">{{ product.name }}</p>
                        <p class="truncate text-sm text-stone-500">{{ product.brand || 'Open Food Facts' }}</p>
                        <p class="mt-1 text-sm font-semibold">
                            {{ product.calories_per_100 }} kcal · P {{ product.protein_per_100 }}g · C {{ product.carbs_per_100 }}g · F {{ product.fat_per_100 }}g / 100{{ product.nutrition_unit }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-2">
                    <button
                        v-for="(option, index) in portionOptions"
                        :key="`${option.quantity}-${option.unit}-${index}`"
                        class="min-h-12 rounded-md border px-3 py-3 text-left text-sm font-bold"
                        :class="selectedPortionKey === String(index) ? 'border-[#253d2c] bg-[#dce8d4]' : 'border-stone-200 bg-white'"
                        @click="selectPortion(option, index)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                    <input
                        v-model.number="barcodeMealForm.portion_quantity"
                        type="number"
                        min="0.1"
                        step="0.1"
                        class="rounded-md border border-stone-200 bg-white px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                    >
                    <select
                        v-model="barcodeMealForm.portion_unit"
                        class="w-full rounded-md border border-stone-200 bg-white px-2 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                    >
                        <option value="g">g</option>
                        <option value="ml">ml</option>
                    </select>
                </div>
                <p v-if="barcodeMealForm.errors.portion_unit" class="mt-1 text-sm font-semibold text-red-700">{{ barcodeMealForm.errors.portion_unit }}</p>

                <div class="rounded-md border border-stone-200 bg-white p-3">
                    <p class="text-sm font-bold">When did you have it?</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 rounded px-3 text-sm font-bold transition"
                            :class="selectedMealType === mealType ? 'bg-[#253d2c] text-white' : 'bg-stone-100 text-stone-600 active:bg-stone-200'"
                            @click="setMealType(mealType)"
                        >
                            {{ mealLabels[mealType] }}
                        </button>
                    </div>
                </div>

                <button class="flex w-full items-center justify-center gap-2 rounded-md bg-[#253d2c] px-4 py-3 font-bold text-white active:bg-[#17211b]" :disabled="barcodeMealForm.processing" @click="addBarcodeMeal">
                    <Plus :size="18" />
                    Add {{ barcodeCalories }} kcal
                </button>
            </div>
        </Card>

        <Card v-if="mode === 'custom'">
            <div class="flex items-center gap-2">
                <Utensils :size="21" class="text-[#d28a45]" />
                <h2 class="font-bold">Custom meal</h2>
            </div>

            <div v-if="previousCustomMeals.length" class="mt-4">
                <p class="text-xs font-bold uppercase text-stone-500">Previous custom meals</p>
                <div class="mt-2 grid gap-2">
                    <button
                        v-for="meal in previousCustomMeals"
                        :key="meal.id"
                        type="button"
                        class="rounded-md border border-stone-200 bg-stone-50 p-3 text-left active:bg-stone-100"
                        @click="selectPreviousCustomMeal(meal)"
                    >
                        <span class="block font-bold">{{ meal.name }}</span>
                        <span class="block text-sm font-semibold text-stone-500">
                            {{ meal.calories }} kcal · P {{ meal.protein_g }}g · C {{ meal.carbs_g }}g · F {{ meal.fat_g }}g
                        </span>
                    </button>
                </div>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addCustomMeal">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-stone-500">Name</span>
                    <input
                        v-model="customMealForm.name"
                        type="text"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="customMealForm.errors.name" class="mt-1 block text-sm font-semibold text-red-700">{{ customMealForm.errors.name }}</span>
                </label>

                <div class="grid grid-cols-3 gap-2">
                    <label v-for="field in [
                        ['protein_g', 'Protein'],
                        ['carbs_g', 'Carbs'],
                        ['fat_g', 'Fat'],
                    ]" :key="field[0]">
                        <span class="text-xs font-bold uppercase text-stone-500">{{ field[1] }}</span>
                        <input
                            v-model.number="customMealForm[field[0]]"
                            type="number"
                            min="0"
                            step="0.1"
                            class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-2 py-3 text-right font-bold outline-none focus:border-[#6f9b58]"
                        >
                    </label>
                </div>

                <div class="rounded-md border border-stone-200 bg-stone-50 p-3">
                    <p class="text-sm font-bold">When did you have it?</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            v-for="mealType in mealTypes"
                            :key="mealType"
                            type="button"
                            class="min-h-11 rounded px-3 text-sm font-bold transition"
                            :class="selectedMealType === mealType ? 'bg-[#253d2c] text-white' : 'bg-white text-stone-600 active:bg-stone-100'"
                            @click="setMealType(mealType)"
                        >
                            {{ mealLabels[mealType] }}
                        </button>
                    </div>
                </div>

                <button class="w-full rounded-md bg-[#253d2c] px-4 py-3 font-bold text-white active:bg-[#17211b]" :disabled="customMealForm.processing">
                    Add {{ customCalories }} kcal
                </button>
            </form>
        </Card>

        <Card v-if="mode === 'workout'">
            <div class="flex items-center gap-2">
                <Dumbbell :size="21" class="text-[#6f9b58]" />
                <h2 class="font-bold">Workout</h2>
            </div>

            <form class="mt-4 space-y-4" @submit.prevent="addWorkout">
                <label class="block">
                    <span class="text-xs font-bold uppercase text-stone-500">Title</span>
                    <input
                        v-model="workoutForm.title"
                        type="text"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="workoutForm.errors.title" class="mt-1 block text-sm font-semibold text-red-700">{{ workoutForm.errors.title }}</span>
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <label>
                        <span class="text-xs font-bold uppercase text-stone-500">Calories burnt</span>
                        <input
                            v-model.number="workoutForm.calories_burned"
                            type="number"
                            min="1"
                            step="1"
                            class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-right font-bold outline-none focus:border-[#6f9b58]"
                        >
                        <span v-if="workoutForm.errors.calories_burned" class="mt-1 block text-sm font-semibold text-red-700">{{ workoutForm.errors.calories_burned }}</span>
                    </label>

                    <label>
                        <span class="text-xs font-bold uppercase text-stone-500">Time</span>
                        <input
                            v-model="workoutForm.time"
                            type="time"
                            class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-bold outline-none focus:border-[#6f9b58]"
                        >
                        <span v-if="workoutForm.errors.time" class="mt-1 block text-sm font-semibold text-red-700">{{ workoutForm.errors.time }}</span>
                    </label>
                </div>

                <button class="flex w-full items-center justify-center gap-2 rounded-md bg-[#253d2c] px-4 py-3 font-bold text-white active:bg-[#17211b]" :disabled="workoutForm.processing">
                    <Plus :size="18" />
                    Add workout
                </button>
            </form>
        </Card>
    </section>
</template>
