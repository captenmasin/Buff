<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { FocusScope } from 'reka-ui';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { BookOpen, Camera, ChevronLeft, ChevronRight, Dumbbell, FileText, LoaderCircle, Pencil, Plus, ScanBarcode, Search as SearchIcon, Utensils, X } from '@lucide/vue';
import { formatDisplayDate } from '../dateFormat';
import {foodSearchUrl, responseErrorMessage} from '../foodRequests';
import { hapticImpact } from '../haptics';
import {mealTypeForHour, type MealSuggestion, type MealType} from '../mealSuggestions';
import { scaleLabelNutrition } from '../nutritionLabel';
import { captureVideoPhoto, resizePhoto } from '../photoResize';
import { photoDataUrl } from '../photoDataUrl';
import MacroSummary from '../Components/Add/MacroSummary.vue';
import MealTypePicker from '../Components/Add/MealTypePicker.vue';
import QuickMeal from '../Components/Add/QuickMeal.vue';
import WorkoutEntryForm from '../Components/Add/WorkoutEntryForm.vue';
import AppSheet from '../Components/AppSheet.vue';
import Card from "../Components/Card.vue";
import FoodThumbnail from '../Components/FoodThumbnail.vue';
import PageHeader from '../Components/PageHeader.vue';
import RecipeMode from '../Components/RecipeMode.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';
import SelectContent from '../Components/ui/select/SelectContent.vue';
import SelectItem from '../Components/ui/select/SelectItem.vue';
import SelectTrigger from '../Components/ui/select/SelectTrigger.vue';
import SelectValue from '../Components/ui/select/SelectValue.vue';
import Textarea from '../Components/ui/textarea/Textarea.vue';
import {isSubscriptionActive} from '../subscriptions';
import {nativeRuntimeAvailable} from '../nativeRuntime';

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
    source_type: string;
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

interface NutritionLabelDraft {
    kind: 'nutrition_label';
    name?: string | null;
    portion_quantity: number;
    portion_unit: 'g' | 'ml' | 'serving';
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

interface SelectedPhoto {
    file: File;
    preview: string;
}

interface RecipeItem {
    name: string;
    food_product_id: string | null;
    portion_quantity: number;
    portion_unit: 'g' | 'ml';
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
}

interface RecipeSummary {
    id: string;
    name: string;
    servings: number;
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    items: RecipeItem[];
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
    recipes?: RecipeSummary[];
    selectedRecipeId?: string | null;
    selectedPreviousEntry?: PreviousMeal | null;
    reminderSuggestion?: MealSuggestion | null;
}>(), {
    meal: null,
    autoScan: false,
    previousFoodEntries: () => [],
    previousCustomMeals: () => [],
    recipes: () => [],
});
const page = usePage<{
    buff: {
        account?: {
            subscription?: {expires_at?: string | null} | null;
        } | null;
    };
}>();
const subscriptionActive = computed(() => isSubscriptionActive(page.props.buff.account?.subscription?.expires_at));

const customMealMacroFields: ReadonlyArray<readonly [MacroField, string]> = [
    ['protein_g', 'Protein'],
    ['carbs_g', 'Carbs'],
    ['fat_g', 'Fat'],
];

function addModeUrl(mode: string, extra: Record<string, string> = {}) {
    const params = new URLSearchParams({
        date: props.date,
        mode,
        ...extra,
    });

    if (props.meal && !params.has('meal')) {
        params.set('meal', props.meal);
    }

    return `/add?${params.toString()}`;
}

const selectedMealType = ref<MealType>(props.meal ?? mealTypeForHour());
const previousMealForm = useForm({
    date: props.date,
    meal_type: selectedMealType.value,
    portion_quantity: null as number | null,
    portion_unit: null as string | null,
    quick_log: false,
});
const barcode = ref('');
const lookupError = ref('');
const manualBarcodeInput = ref<InstanceType<typeof Input> | null>(null);
const lookupLoading = ref(false);
const nativeMessage = ref('');
const nativeBridge = ref<NativeBridge | null>(null);
const scannerStarting = ref(false);
const webScannerOpen = ref(false);
const webScannerReady = ref(false);
const webScannerVideo = ref<HTMLVideoElement | null>(null);
const webScannerControls = ref<{ stop(): void } | null>(null);
const scanSuccessFlash = ref(false);
let scanSuccessFlashTimer: number | null = null;
const product = ref<FoodProduct | null>(null);
const portionOptions = ref<PortionOption[]>([]);
const selectedPortionKey = ref('');
const manualBarcodeOpen = ref(false);
const foodSearch = ref('');
const foodSearchError = ref('');
const foodSearchLoading = ref(false);
const foodSearchResults = ref<FoodSearchResult[]>([]);
const showAllRecentFoods = ref(false);
const recentFoodLimit = 3;
const selectedPreviousMeal = ref<PreviousMeal | null>(null);
const previousMealPortionQuantity = ref<number | null>(null);
const previousMealPortionUnit = ref('g');
let foodSearchRequestId = 0;
let foodSearchTimer = 0;
const photoCameraInput = ref<HTMLInputElement | null>(null);
const photoLibraryInput = ref<HTMLInputElement | null>(null);
const labelPhotoInput = ref<HTMLInputElement | null>(null);
const labelCameraOpen = ref(false);
const labelCameraError = ref('');
const scannerHeading = ref<HTMLHeadingElement | null>(null);
let labelCameraStream: MediaStream | null = null;
let labelCameraSession = 0;
let barcodeCameraSession = 0;
const labelScanButtons = ref<InstanceType<typeof Button>[]>([]);
const mealReviewHeading = ref<HTMLHeadingElement | null>(null);
const previousCustomFoodList = ref<HTMLDetailsElement | null>(null);
const nutritionLabel = ref<NutritionLabelDraft | null>(null);
const labelLoading = ref(false);
const labelError = ref('');
let labelRequest: AbortController | null = null;
const selectedPhotos = ref<SelectedPhoto[]>([]);
const photoProcessing = ref(false);
const photoNote = ref('');
const photoAnalysisLoading = ref(false);
const photoAnalysisError = ref('');
const subscriptionRequired = ref(false);
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
    calories: null as number | '' | null,
    protein_g: '',
    carbs_g: '',
    fat_g: '',
    analysis_id: '',
});

const barcodeMealForm = useForm({
    date: props.date,
    meal_type: selectedMealType.value,
    add_method: 'barcode',
    food_product_id: '',
    portion_quantity: 100,
    portion_unit: 'g',
});

const customNutrition = computed(() => {
    const nutrition = {
        calories: customMealForm.calories === '' ? null : customMealForm.calories,
        protein_g: Number(customMealForm.protein_g || 0),
        carbs_g: Number(customMealForm.carbs_g || 0),
        fat_g: Number(customMealForm.fat_g || 0),
    };

    return nutritionLabel.value
        ? scaleLabelNutrition(nutrition, Number(customMealForm.portion_quantity), nutritionLabel.value.portion_quantity)
        : nutrition;
});
const customCalories = computed(() => {
    const {calories, protein_g, carbs_g, fat_g} = customNutrition.value;

    return calories ?? Math.round(protein_g * 4 + carbs_g * 4 + fat_g * 9);
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
    const entry = selectedPreviousMeal.value;

    return entry !== null && Number(entry.portion_quantity) > 0;
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
        label: previousMealPortionLabel(selectedPreviousMeal.value),
        quantity: selectedPreviousMeal.value.portion_quantity,
        unit: selectedPreviousMeal.value.portion_unit || previousMealPortionUnit.value,
    }];
});
const selectedPortion = computed(() => selectedPortionKey.value === '' ? null : activeFoodPortionOptions.value[Number(selectedPortionKey.value)] ?? null);

const displayDate = computed(() => formatDisplayDate(props.date));
const foodSearchQuery = computed(() => foodSearch.value.trim());
const subscriptionPaywallTitle = computed(() => {
    if (subscriptionActive.value) return null;
    if (props.mode === 'photo') return 'Photo analysis requires Buff+';
    if (props.mode === 'label') return 'Label scanning requires Buff+';

    return null;
});
const showFoodSearch = computed(() => ['choose', 'food', 'label'].includes(props.mode) && !subscriptionPaywallTitle.value && !nutritionLabel.value);
const visibleFoods = computed(() => {
    if (foodSearchQuery.value) return foodSearchResults.value;

    return showAllRecentFoods.value ? props.previousFoodEntries : props.previousFoodEntries.slice(0, recentFoodLimit);
});
const loggingLinks = [
    { mode: 'custom', label: 'Custom food', icon: Pencil, tone: 'bg-muted text-foreground' },
    { mode: 'photo', label: 'Photo meal', icon: Camera, tone: 'bg-primary-container text-primary-container-foreground' },
    { mode: 'label' },
    { mode: 'recipe', label: 'Recipes', icon: BookOpen, tone: 'bg-secondary text-secondary-foreground' },
    { mode: 'workout', label: 'Add workout', icon: Dumbbell, tone: 'bg-success-soft text-success-soft-foreground' },
] as const;
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

function addSelectedPortion() {
    if (!selectedPortion.value) return;

    activeFoodPortionQuantity.value = Math.round((Number(activeFoodPortionQuantity.value) + selectedPortion.value.quantity) * 100) / 100;
}

function roundMacro(value: number) {
    return Math.round(value * 10) / 10;
}

function formatMacro(value: number) {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

function previousMealPortionLabel(meal: PreviousMeal): string {
    if (meal.portion_quantity === null) {
        return '';
    }

    const quantity = formatMacro(Number(meal.portion_quantity));

    return meal.portion_unit === null
        ? `${quantity} serving${Number(meal.portion_quantity) === 1 ? '' : 's'}`
        : `${quantity}${meal.portion_unit}`;
}

function portionOptionLabel(option: PortionOption) {
    return option.label || `${option.quantity}${option.unit}`;
}

async function notifyBarcodeFound(code: string): Promise<void> {
    scanSuccessFlash.value = true;

    if (scanSuccessFlashTimer !== null) {
        window.clearTimeout(scanSuccessFlashTimer);
    }

    scanSuccessFlashTimer = window.setTimeout(() => {
        scanSuccessFlash.value = false;
        scanSuccessFlashTimer = null;
    }, 700);

    await hapticImpact(40);
    window.dispatchEvent(new CustomEvent('buff:toast', { detail: `Barcode found · ${code}` }));
}

async function lookup(scannedBarcode: string | null = null) {
    if (lookupLoading.value) return;

    const manualSession = manualBarcodeOpen.value ? barcodeCameraSession : null;
    lookupError.value = '';
    lookupLoading.value = true;
    selectedPreviousMeal.value = null;

    try {
        const response = await axios.post('/barcode/lookup', {
            barcode: scannedBarcode || barcode.value,
        }, {timeout: 15_000});

        if (manualSession !== null && manualSession !== barcodeCameraSession) return;

        if (manualBarcodeOpen.value) stopWebScan();

        product.value = response.data.product;
        portionOptions.value = response.data.portion_options || [];
        barcode.value = response.data.product.barcode;
        barcodeMealForm.food_product_id = response.data.product.id;
        barcodeMealForm.add_method = 'barcode';
        barcodeMealForm.portion_unit = response.data.product.nutrition_unit || 'g';

        if (!scannedBarcode) {
            await hapticImpact();
        }

        if (portionOptions.value.length > 0) {
            selectPortion(portionOptions.value[0], 0);
        }
    } catch (error) {
        if (manualSession !== null && manualSession !== barcodeCameraSession) return;

        product.value = null;
        lookupError.value = responseErrorMessage(error, 'barcode', 'Could not look up that barcode.');
    } finally {
        lookupLoading.value = false;
    }
}

function queueFoodSearch() {
    window.clearTimeout(foodSearchTimer);
    const query = foodSearch.value.trim();
    const requestId = ++foodSearchRequestId;

    foodSearchError.value = '';

    if (query.length < 2) {
        foodSearchResults.value = [];
        foodSearchLoading.value = false;

        return;
    }

    foodSearchLoading.value = true;
    foodSearchTimer = window.setTimeout(() => void searchFoodProducts(query, requestId), 250);
}

function retryFoodSearch(): void {
    window.clearTimeout(foodSearchTimer);

    const query = foodSearch.value.trim();
    const requestId = ++foodSearchRequestId;

    void searchFoodProducts(query, requestId);
}

async function searchFoodProducts(query: string, requestId: number): Promise<void> {
    foodSearchError.value = '';

    if (query.length < 2) {
        foodSearchResults.value = [];
        foodSearchLoading.value = false;
        return;
    }

    foodSearchLoading.value = true;

    try {
        const {data} = await axios.get(foodSearchUrl(query, navigator.language));

        if (requestId === foodSearchRequestId) {
            foodSearchResults.value = data.products || [];
        }
    } catch (error) {
        if (requestId === foodSearchRequestId) {
            foodSearchResults.value = [];
            foodSearchError.value = responseErrorMessage(error, 'q', 'Food search is unavailable. Check your connection and try again.');
        }
    } finally {
        if (requestId === foodSearchRequestId) {
            foodSearchLoading.value = false;
        }
    }
}

function selectFoodProduct(foodProduct: FoodProduct) {
    barcodeMealForm.clearErrors();
    selectedPreviousMeal.value = null;
    product.value = foodProduct;
    portionOptions.value = [
        { label: `100${foodProduct.nutrition_unit || 'g'}`, quantity: 100, unit: foodProduct.nutrition_unit || 'g' },
    ];
    barcode.value = foodProduct.barcode;
    barcodeMealForm.food_product_id = foodProduct.id;
    barcodeMealForm.add_method = 'search';
    barcodeMealForm.portion_unit = foodProduct.nutrition_unit || 'g';
    selectPortion(portionOptions.value[0], 0);
    hapticImpact();
}

function selectPreviousMeal(meal: PreviousMeal, feedback = true) {
    previousMealForm.clearErrors();
    selectedPreviousMeal.value = meal;
    product.value = null;
    previousMealPortionQuantity.value = meal.portion_quantity;
    previousMealPortionUnit.value = meal.portion_unit ?? 'servings';
    selectedPortionKey.value = meal.portion_quantity ? '0' : '';
    if (feedback) hapticImpact();
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
    stopWebScan();
    manualBarcodeOpen.value = true;
    webScannerOpen.value = true;
    scannerStarting.value = false;
    void nextTick(() => manualBarcodeInput.value?.$el.focus());
}

async function startWebScan() {
    stopWebScan();
    const session = ++barcodeCameraSession;
    nativeMessage.value = '';
    scannerStarting.value = true;
    webScannerOpen.value = true;
    webScannerReady.value = false;

    await nextTick();

    if (!navigator.mediaDevices?.getUserMedia) {
        nativeMessage.value = 'Camera scanning is not available on this device. Enter the barcode manually.';
        showManualBarcodeInput();
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

        if (session !== barcodeCameraSession) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }

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

                if (session !== barcodeCameraSession || !scanned || lookupLoading.value) {
                    return;
                }

                barcode.value = scanned;
                void notifyBarcodeFound(scanned);
                stopWebScan();
                void lookup(scanned);
            },
        );

        if (session === barcodeCameraSession) webScannerControls.value = controls;
        else controls.stop();
    } catch (error) {
        if (session === barcodeCameraSession) {
            nativeMessage.value = cameraErrorMessage(error);
            showManualBarcodeInput();
        }
    } finally {
        if (session === barcodeCameraSession) scannerStarting.value = false;
    }
}

function stopWebScan() {
    barcodeCameraSession++;
    if (labelCameraOpen.value) {
        labelCameraSession++;
        labelCameraStream?.getTracks().forEach((track) => track.stop());
        labelCameraStream = null;
        if (webScannerVideo.value) {
            webScannerVideo.value.srcObject = null;
        }
        labelCameraOpen.value = false;
        scannerStarting.value = false;

        if (!labelLoading.value) {
            void nextTick(() => labelScanButtons.value[0]?.$el.focus());
        }
    }

    webScannerControls.value?.stop();
    webScannerControls.value = null;
    webScannerReady.value = false;
    webScannerOpen.value = false;
    manualBarcodeOpen.value = false;
    scannerStarting.value = false;
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
        void notifyBarcodeFound(scanned);
        void lookup(scanned);
    }
}

function addBarcodeMeal() {
    barcodeMealForm.meal_type = selectedMealType.value;
    hapticImpact();
    barcodeMealForm.post('/meals/barcode');
}

function addPreviousMeal() {
    const entry = selectedPreviousMeal.value;
    if (!entry || previousMealForm.processing) return;

    hapticImpact();
    previousMealForm.date = props.date;
    previousMealForm.meal_type = selectedMealType.value;
    previousMealForm.portion_quantity = previousMealHasPortion.value ? previousMealPortionQuantity.value : null;
    previousMealForm.portion_unit = previousMealHasPortion.value ? entry.portion_unit : null;
    previousMealForm.quick_log = props.selectedPreviousEntry?.id === entry.id;

    if (previousMealHasPortion.value && Number(previousMealForm.portion_quantity) < 0.1) {
        previousMealForm.setError('portion_quantity', 'Enter a portion of at least 0.1.');
        return;
    }

    previousMealForm.post(`/meals/${entry.id}/repeat`, {preserveScroll: true});
}

function closeFoodAddSheet() {
    barcodeMealForm.clearErrors();
    previousMealForm.clearErrors();
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
            ...customNutrition.value,
        }))
        .post('/meals/custom', { preserveScroll: 'errors' });
}

async function openLabelCamera() {
    if (!subscriptionActive.value) return;

    stopWebScan();
    const session = ++labelCameraSession;
    labelCameraOpen.value = true;
    labelCameraError.value = '';
    scannerStarting.value = true;
    webScannerOpen.value = true;
    await nextTick();
    if (session !== labelCameraSession) return;

    scannerHeading.value?.focus();
    const video = webScannerVideo.value;
    try {
        if (!video || !navigator.mediaDevices?.getUserMedia) {
            labelCameraError.value = 'Camera access is unavailable in this browser. Choose a photo of the label.';
            return;
        }

        const stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {facingMode: {ideal: 'environment'}, width: {ideal: 1920}, height: {ideal: 1080}},
        });
        if (session !== labelCameraSession) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }

        labelCameraStream = stream;
        video.srcObject = stream;
        await video.play();
    } catch (error) {
        if (session !== labelCameraSession) return;

        labelCameraStream?.getTracks().forEach((track) => track.stop());
        labelCameraStream = null;
        if (video) video.srcObject = null;
        webScannerReady.value = false;
        labelCameraError.value = error instanceof DOMException && error.name === 'NotAllowedError'
            ? 'Camera access was denied. Allow camera access in your browser or choose a photo.'
            : 'Could not open the camera. Choose a photo of the label.';
    } finally {
        if (session === labelCameraSession) {
            scannerStarting.value = false;
        }
    }
}

function captureNutritionLabel() {
    const video = webScannerVideo.value;
    if (video && webScannerReady.value) {
        void scanNutritionLabel(() => captureVideoPhoto(video));
    }
}

function selectNutritionLabelPhoto(event: Event) {
    const input = event.target as HTMLInputElement;
    const photo = input.files?.[0];
    input.value = '';

    if (photo) {
        void scanNutritionLabel(() => resizePhoto(photo));
    }
}

async function scanNutritionLabel(preparePhoto: () => Promise<File>) {
    if (!subscriptionActive.value || labelLoading.value) return;

    const request = new AbortController();
    labelRequest = request;
    labelLoading.value = true;
    labelError.value = '';
    subscriptionRequired.value = false;

    try {
        const photo = preparePhoto();
        stopWebScan();
        const image = await photoDataUrl(await photo);
        const {data} = await axios.post('/meal-analyses', {
            kind: 'nutrition_label',
            photos: [image],
        }, {signal: request.signal});
        const draft: NutritionLabelDraft | undefined = data.analysis?.draft;

        if (draft?.kind !== 'nutrition_label') {
            throw new Error('Invalid nutrition label response');
        }

        closeFoodAddSheet();
        customMealForm.clearErrors();
        customMealForm.name = draft.name ?? '';
        customMealForm.portion_quantity = Number(draft.portion_quantity);
        customMealForm.portion_unit = draft.portion_unit;
        customMealForm.calories = Number(draft.calories);
        customMealForm.protein_g = String(draft.protein_g);
        customMealForm.carbs_g = String(draft.carbs_g);
        customMealForm.fat_g = String(draft.fat_g);
        nutritionLabel.value = draft;
        await nextTick();
        mealReviewHeading.value?.focus();
    } catch (error) {
        if (!request.signal.aborted) {
            subscriptionRequired.value = axios.isAxiosError(error) && error.response?.data?.code === 'subscription_required';
            labelError.value = responseErrorMessage(error, 'photos.0', 'Could not read the nutrition label. Try a clearer photo or add the food manually.');
        }
    } finally {
        labelLoading.value = false;
        labelRequest = null;
    }
}

async function cancelNutritionLabel() {
    nutritionLabel.value = null;
    customMealForm.reset().clearErrors();
    await nextTick();
    labelScanButtons.value[0]?.$el.focus();
}

async function selectPhotos(event: Event) {
    const input = event.target instanceof HTMLInputElement ? event.target : null;
    const files = Array.from(input?.files ?? []).slice(0, 3 - selectedPhotos.value.length);

    if (files.length === 0) {
        if (input) {
            input.value = '';
        }

        return;
    }

    photoProcessing.value = true;

    try {
        for (const file of files) {
            const resized = await resizePhoto(file);
            selectedPhotos.value.push({file: resized, preview: URL.createObjectURL(resized)});
        }
    } finally {
        photoProcessing.value = false;

        if (input) {
            input.value = '';
        }
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
    customMealForm.calories = null;
    customMealForm.protein_g = String(draft.protein_g);
    customMealForm.carbs_g = String(draft.carbs_g);
    customMealForm.fat_g = String(draft.fat_g);
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
    subscriptionRequired.value = false;

    try {
        const photos = await Promise.all(selectedPhotos.value.map(({file}) => photoDataUrl(file)));
        const response = await axios.post('/meal-analyses', {photos, note: photoNote.value});
        const analysis = response.data.analysis;
        const draft = analysis?.draft;

        if (!analysis?.id || !draft) {
            throw new Error('Invalid analysis');
        }

        applyAnalysisDraft(analysis);
    } catch (error) {
        const code = axios.isAxiosError(error) ? error.response?.data?.code : null;
        subscriptionRequired.value = code === 'subscription_required';
        photoAnalysisError.value = {
            subscription_required: 'Buff+ is required for AI meal analysis. Subscribe or restore from Settings.',
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
    subscriptionRequired.value = false;

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
        const code = axios.isAxiosError(error) ? error.response?.data?.code : null;
        subscriptionRequired.value = code === 'subscription_required';
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

function selectPreviousCustomMeal(meal: PreviousMeal) {
    customMealForm.name = meal.name;
    customMealForm.portion_quantity = meal.portion_quantity ?? 100;
    customMealForm.portion_unit = meal.portion_unit ?? 'serving';
    customMealForm.protein_g = String(meal.protein_g);
    customMealForm.carbs_g = String(meal.carbs_g);
    customMealForm.fat_g = String(meal.fat_g);
    const macroCalories = Math.round(meal.protein_g * 4 + meal.carbs_g * 4 + meal.fat_g * 9);
    customMealForm.calories = meal.calories === macroCalories ? null : meal.calories;
    customMealForm.clearErrors();

    if (previousCustomFoodList.value) previousCustomFoodList.value.open = false;
}

watch(() => props.meal, (meal) => setMealType(meal ?? mealTypeForHour()));
watch(subscriptionActive, (active) => {
    if (active) return;

    if (labelCameraOpen.value) stopWebScan();
    labelRequest?.abort();
});
watch(() => props.selectedPreviousEntry?.id, () => {
    if (props.selectedPreviousEntry) selectPreviousMeal(props.selectedPreviousEntry, false);
}, {immediate: true});

onMounted(async () => {
    if (nativeRuntimeAvailable()) {
        try {
            const native = await import('#nativephp');
            nativeBridge.value = native;
            native.On(native.Events.Scanner.CodeScanned, handleScan);
        } catch {
            nativeBridge.value = null;
        }
    }

    if (props.mode === 'food' && props.autoScan) {
        startScan();
    }
});

onUnmounted(() => {
    labelRequest?.abort();
    window.clearTimeout(foodSearchTimer);

    if (scanSuccessFlashTimer !== null) {
        window.clearTimeout(scanSuccessFlashTimer);
    }

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
            {{ mode === 'label' && subscriptionPaywallTitle ? 'Scan label' : mode === 'food' || mode === 'recipe' ? 'Add food' : mode === 'custom' ? 'Custom food' : mode === 'photo' ? 'Photo meal' : mode === 'workout' ? 'Add workout' : 'Add' }}
            <span v-if="meal"> — {{ meal.charAt(0).toUpperCase() + meal.slice(1) }}</span>
            <template #leading>
                <Button :as="Link" :href="`/?date=${date}`" variant="ghost" size="icon" class="-ml-2 rounded-full" aria-label="Back to today">
                    <ChevronLeft :size="26" stroke-width="2.2" />
                </Button>
            </template>
        </PageHeader>

        <nav v-if="mode === 'recipe'" class="grid grid-cols-2 gap-1 rounded-xl bg-secondary/70 p-1" aria-label="Food source">
            <Button
                :as="Link"
                :href="addModeUrl('food')"
                variant="ghost"
                class="h-11 w-full text-muted-foreground hover:bg-card/60"
            >
                All foods
            </Button>
            <Button
                :as="Link"
                :href="addModeUrl('recipe')"
                variant="ghost"
                class="h-11 w-full bg-card text-foreground shadow-sm hover:bg-card"
                aria-current="page"
            >
                Recipes
            </Button>
        </nav>

        <Transition
            enter-active-class="transition-[opacity,transform] duration-sheet ease-drawer motion-reduce:duration-150 motion-reduce:transition-opacity"
            enter-from-class="translate-y-full opacity-0 motion-reduce:translate-y-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-[opacity,transform] duration-sheet ease-drawer motion-reduce:duration-150 motion-reduce:transition-opacity"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-full opacity-0 motion-reduce:translate-y-0"
        >
            <FocusScope v-if="webScannerOpen" loop trapped role="dialog" aria-modal="true" aria-labelledby="add-scanner-heading" data-motion-transform class="fixed inset-0 z-50 flex flex-col bg-foreground text-background" @keydown.esc.stop="stopWebScan">
            <div class="flex items-center justify-between gap-3 px-4 py-3 pt-[calc(env(safe-area-inset-top,0px)+0.75rem)]">
                <div class="min-w-0">
                    <p v-if="!labelCameraOpen" class="text-sm text-background/70">{{ manualBarcodeOpen ? 'Manual barcode' : 'Scan barcode' }}</p>
                    <h2 id="add-scanner-heading" ref="scannerHeading" tabindex="-1" class="text-xl font-semibold">
                        {{ labelCameraOpen ? 'Scan nutrition label' : scannerStarting ? 'Opening camera...' : manualBarcodeOpen ? 'Enter barcode' : 'Point camera at barcode' }}
                    </h2>
                </div>
                <Button variant="ghost" size="icon" class="h-11 w-11 shrink-0 bg-background/10 text-background active:bg-background/15" aria-label="Close scanner" @click="stopWebScan">
                    <X :size="22" />
                </Button>
            </div>

            <div class="relative min-h-0 flex-1">
                <div v-if="labelCameraOpen && labelCameraError" class="absolute inset-0 z-10 grid place-items-center px-6 text-center" role="alert">
                    <p>{{ labelCameraError }}</p>
                </div>
                <div v-else-if="!manualBarcodeOpen && !webScannerReady" class="absolute inset-0 z-10 grid h-full w-full place-items-center bg-foreground">
                    <LoaderCircle :size="34" class="animate-spin text-background/70" />
                    <span v-if="labelCameraOpen" class="sr-only" role="status">Opening camera…</span>
                </div>

                <video
                    v-show="!manualBarcodeOpen"
                    :key="labelCameraOpen ? 'label' : 'barcode'"
                    ref="webScannerVideo"
                    class="scanner-video h-full w-full bg-foreground transition-opacity duration-150"
                    :class="[webScannerReady ? 'opacity-100' : 'opacity-0', labelCameraOpen ? 'object-contain' : 'object-cover']"
                    autoplay
                    muted
                    playsinline
                    disablepictureinpicture
                    controlslist="nodownload noplaybackrate noremoteplayback"
                    @loadeddata="markWebScannerReady"
                    @playing="markWebScannerReady"
                />

                <div
                    v-if="!labelCameraOpen && !manualBarcodeOpen"
                    class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center px-10"
                    aria-hidden="true"
                >
                    <div
                        class="relative aspect-[5/3] w-full max-w-sm rounded-2xl border-2 transition-[border-color,box-shadow,background-color] duration-200"
                        :class="scanSuccessFlash
                            ? 'border-success bg-success/15 shadow-[0_0_0_9999px_rgb(15_17_37_/_0.45)]'
                            : 'border-background/85 shadow-[0_0_0_9999px_rgb(15_17_37_/_0.45)]'"
                    >
                        <span class="absolute -left-0.5 -top-0.5 h-7 w-7 rounded-tl-2xl border-l-4 border-t-4 border-background" />
                        <span class="absolute -right-0.5 -top-0.5 h-7 w-7 rounded-tr-2xl border-r-4 border-t-4 border-background" />
                        <span class="absolute -bottom-0.5 -left-0.5 h-7 w-7 rounded-bl-2xl border-b-4 border-l-4 border-background" />
                        <span class="absolute -bottom-0.5 -right-0.5 h-7 w-7 rounded-br-2xl border-b-4 border-r-4 border-background" />
                        <span
                            class="absolute inset-x-5 top-1/2 h-0.5 -translate-y-1/2 rounded-full"
                            :class="scanSuccessFlash ? 'bg-success' : 'bg-background/70'"
                        />
                    </div>
                </div>

                <div
                    v-if="scanSuccessFlash && !labelCameraOpen && !manualBarcodeOpen"
                    class="pointer-events-none absolute inset-x-0 bottom-6 z-30 flex justify-center px-4"
                    role="status"
                >
                    <p class="rounded-full bg-success px-4 py-2 text-sm font-semibold text-success-foreground shadow-lg">
                        Barcode found
                    </p>
                </div>

                <div v-if="manualBarcodeOpen" class="flex h-full items-center px-4">
                    <form class="w-full rounded-xl bg-card p-4 text-foreground" @submit.prevent="lookup()">
                        <label class="block">
                            <span class="field-label">Barcode</span>
                            <Input
                                ref="manualBarcodeInput"
                                v-model="barcode"
                                type="text"
                                inputmode="numeric"
                                enterkeyhint="search"
                                required
                                :aria-invalid="Boolean(lookupError)"
                                :aria-describedby="lookupError ? 'manual-barcode-error' : undefined"
                                placeholder="Enter barcode"
                                class="mt-1"
                                @update:model-value="lookupError = ''"
                            />
                        </label>
                        <p v-if="lookupError" id="manual-barcode-error" class="mt-2 text-sm text-destructive" role="alert">{{ lookupError }}</p>
                        <p v-else-if="nativeMessage" class="mt-2 text-sm text-muted-foreground">{{ nativeMessage }}</p>
                        <Button type="submit" class="mt-3 w-full" :loading="lookupLoading" loading-label="Looking up barcode…">
                            <Search :size="19" />
                            Look up barcode
                        </Button>
                        <Button v-if="lookupError" :as="Link" :href="addModeUrl('custom')" variant="ghost" class="mt-2 w-full" @click="stopWebScan">Add custom food</Button>
                    </form>
                </div>
            </div>

            <div class="grid gap-2 px-4 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-3">
                <template v-if="labelCameraOpen">
                    <input ref="labelPhotoInput" type="file" accept="image/*" hidden @change="selectNutritionLabelPhoto">
                    <Button
                        v-if="!labelCameraError"
                        type="button"
                        variant="inverse"
                        class="h-12 w-full"
                        :disabled="scannerStarting || !webScannerReady"
                        @click="captureNutritionLabel"
                    >
                        <Camera :size="18" />
                        Take photo
                    </Button>
                    <Button type="button" variant="inverse" class="h-12 w-full" @click="labelPhotoInput?.click()">
                        Choose photo
                    </Button>
                </template>
                <template v-else>
                <Button v-if="!manualBarcodeOpen" variant="inverse" class="w-full" @click="showManualBarcodeInput">
                    Enter barcode manually
                </Button>
                <Button
                    :as="subscriptionActive ? 'button' : Link"
                    :type="subscriptionActive ? 'button' : undefined"
                    :href="subscriptionActive ? undefined : addModeUrl('label')"
                    variant="inverse"
                    class="h-auto min-h-12 w-full"
                    :disabled="subscriptionActive && scannerStarting"
                    @click="subscriptionActive ? openLabelCamera() : stopWebScan()"
                >
                    <FileText :size="18" aria-hidden="true" />
                    <span>
                        Scan label
                        <span v-if="!subscriptionActive" class="block text-xs text-muted-foreground">Buff+ required</span>
                    </span>
                </Button>
                </template>
            </div>
            </FocusScope>
        </Transition>

        <RecipeMode
            v-if="mode === 'recipe'"
            :key="selectedRecipeId ?? 'recipes'"
            :date="date"
            :meal-types="mealTypes"
            :recipes="recipes"
            :meal="meal"
            :selected-recipe-id="selectedRecipeId"
        />

        <Card v-if="subscriptionPaywallTitle" class="gap-3">
            <h2 class="card-title">{{ subscriptionPaywallTitle }}</h2>
            <Button :as="Link" href="/settings/subscription">View Buff+</Button>
            <Button :as="Link" :href="addModeUrl('food')" variant="surface">Search foods</Button>
        </Card>

        <Card v-else-if="mode === 'photo' && !analysisContext">
            <div class="flex items-center gap-2">
                <Camera :size="21" class="text-food" />
                <h2 class="card-title">Analyze meal photos</h2>
            </div>
            <p class="mt-2 text-sm text-muted-foreground">Add up to three clear angles. Nothing is logged until you review and save.</p>

            <TransitionGroup
                v-if="selectedPhotos.length || photoProcessing"
                tag="div"
                appear
                class="mt-4 grid grid-cols-3 gap-2"
                enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:duration-100 motion-reduce:transition-opacity"
                enter-from-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
                enter-to-class="scale-100 opacity-100"
                leave-active-class="transition-[opacity,transform] duration-150 ease-in-out motion-reduce:duration-100 motion-reduce:transition-opacity"
                leave-from-class="scale-100 opacity-100"
                leave-to-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
            >
                <div
                    v-for="(photo, index) in selectedPhotos"
                    :key="photo.preview"
                    data-motion-transform
                    class="relative overflow-hidden rounded-2xl border border-link/50 bg-muted"
                    :class="selectedPhotos.length === 1 ? 'col-span-3 mx-auto aspect-[302/270] w-full max-w-sm' : 'aspect-square'"
                >
                    <img :src="photo.preview" alt="Selected meal" class="h-full w-full object-cover">
                    <span v-if="index === 0" class="absolute left-2.5 top-2.5 rounded-full bg-primary-container px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-primary-container-foreground">
                        {{ selectedPhotos.length }} of 3 · ready
                    </span>
                    <Button type="button" size="icon" variant="inverse" class="absolute right-1 top-1 h-8 w-8" aria-label="Remove photo" @click="removePhoto(index)">
                        <X :size="16" />
                    </Button>
                </div>
                <div v-if="photoProcessing" key="processing" data-motion-transform class="flex aspect-square flex-col items-center justify-center gap-2 rounded-xl bg-muted text-muted-foreground" role="status" aria-live="polite">
                    <LoaderCircle :size="20" class="motion-safe:animate-spin" />
                    <span class="text-xs font-medium">Preparing photo…</span>
                </div>
            </TransitionGroup>

            <input
                ref="photoCameraInput"
                type="file"
                accept="image/*"
                capture="environment"
                class="hidden"
                @change="selectPhotos"
            >
            <input
                ref="photoLibraryInput"
                type="file"
                accept="image/*"
                multiple
                class="hidden"
                @change="selectPhotos"
            >
            <div v-if="selectedPhotos.length < 3" class="mt-4 grid grid-cols-2 gap-2">
                <Button type="button" variant="surface" :disabled="photoProcessing" @click="photoCameraInput?.click()">
                    <Camera :size="18" />
                    Take photo
                </Button>
                <Button type="button" variant="surface" :disabled="photoProcessing" @click="photoLibraryInput?.click()">
                    <Plus :size="18" />
                    Choose photo
                </Button>
            </div>

            <label class="mt-4 block">
                <span class="field-label">Context (optional)</span>
                <Textarea v-model="photoNote" maxlength="1000" rows="3" class="mt-1" placeholder="Sauce, hidden ingredients, or portion notes" />
            </label>

            <p v-if="photoAnalysisError" class="mt-3 rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert">
                {{ photoAnalysisError }}
            </p>
            <Button v-if="subscriptionRequired" :as="Link" href="/settings/subscription" variant="surface" class="mt-2 w-full">View Buff+</Button>

            <Button
                type="button"
                class="mt-4 h-14 w-full rounded-xl"
                :disabled="photoProcessing || selectedPhotos.length === 0"
                :loading="photoAnalysisLoading"
                loading-label="Analyzing meal…"
                @click="analyzePhotos"
            >
                Analyze meal
            </Button>

        </Card>

        <div v-if="photoAnalysisLoading" class="fixed inset-0 z-50 overflow-y-auto bg-background/95 px-6 py-10 backdrop-blur-sm" role="status" aria-live="polite" aria-label="Buff Vision is analyzing your meal">
            <div class="mx-auto w-full max-w-sm">
                <h2 class="page-title">See what Buff sees.</h2>
                <p class="mt-1 text-sm text-muted-foreground">Your meal is becoming an editable macro estimate.</p>

                <div class="mt-5 rounded-3xl bg-brand-night p-5 text-brand-white">
                    <p class="vision-status text-xs font-semibold uppercase tracking-wider">Buff Vision · analyzing</p>

                    <div class="relative mt-4 aspect-[302/270] overflow-hidden rounded-2xl border border-brand-acid/70 bg-brand-night">
                        <img v-if="selectedPhotos[0]" :src="selectedPhotos[0].preview" alt="" class="h-full w-full object-cover">
                        <div class="vision-grid absolute inset-0" aria-hidden="true" />
                        <div class="vision-scan-line absolute inset-x-0" aria-hidden="true" />
                    </div>

                    <p class="mt-4 text-base font-semibold">Reading ingredients and portions…</p>
                    <p class="mt-2 text-xs leading-relaxed text-pretty text-brand-white/65">Estimating the plate, then building an editable macro draft.</p>
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-brand-white/15" aria-hidden="true">
                        <div class="vision-progress h-full rounded-full bg-brand-acid" />
                    </div>
                </div>

                <p class="mt-4 text-xs font-medium text-muted-foreground">Nothing is logged until you review and save.</p>
            </div>
        </div>

        <div v-if="showFoodSearch" class="grid items-start gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] md:gap-4">
            <div class="grid min-w-0 gap-3">
                <Card class="gap-4 px-4 py-4 sm:px-5">
                    <form role="search" action="/food-products/search" aria-labelledby="add-food-search-label" @submit.prevent="retryFoodSearch">
                        <label id="add-food-search-label" for="add-food-search" class="mb-2 block text-lg font-semibold tracking-tight">Search foods</label>
                        <div class="relative">
                            <SearchIcon :size="18" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                            <Input
                                id="add-food-search"
                                v-model="foodSearch"
                                name="q"
                                type="search"
                                enterkeyhint="search"
                                autocomplete="off"
                                placeholder="Food name"
                                class="bg-muted/45 pl-10"
                                @input="queueFoodSearch"
                            />
                        </div>
                    </form>

                    <div v-if="foodSearchLoading" class="flex items-center gap-2 rounded-xl bg-muted p-3 text-sm text-muted-foreground" role="status">
                        <LoaderCircle :size="17" class="motion-safe:animate-spin text-link" aria-hidden="true" />
                        Searching…
                    </div>

                    <div v-else-if="foodSearchError" class="rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert">
                        <p>{{ foodSearchError }}</p>
                        <Button type="button" variant="ghost" class="mt-2" @click="retryFoodSearch">Retry</Button>
                    </div>

                    <div v-else-if="visibleFoods.length" class="min-w-0">
                        <h2 class="text-lg font-semibold tracking-tight">{{ foodSearchQuery ? 'Search results' : 'Recent foods' }}</h2>
                        <ul id="add-food-results" class="mt-1 divide-y divide-border">
                            <li v-for="result in visibleFoods" :key="result.id" class="min-w-0">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="h-auto min-h-13 w-full min-w-0 justify-start gap-2.5 rounded-lg px-0.5 py-2 text-left"
                                    @click="selectFoodResult(result)"
                                >
                                    <FoodThumbnail :src="result.image_url" :icon="result.type === 'previous_meal' ? 'utensils' : 'barcode'" class="size-10" :icon-size="18" aria-hidden="true" />
                                    <span class="min-w-0 flex-1 whitespace-normal wrap-anywhere">
                                        <span class="block font-semibold">{{ result.name }}</span>
                                        <span v-if="result.type === 'previous_meal'" class="mt-0.5 block text-xs text-muted-foreground">
                                            <template v-if="result.portion_quantity">{{ previousMealPortionLabel(result) }} · </template>{{ result.calories }} kcal
                                        </span>
                                        <span v-else class="mt-0.5 block text-xs text-muted-foreground">
                                            <template v-if="result.brand">{{ result.brand }} · </template>{{ Math.round(result.calories_per_100) }} kcal per 100 {{ result.nutrition_unit || 'g' }}
                                        </span>
                                    </span>
                                    <ChevronRight :size="16" class="text-muted-foreground" aria-hidden="true" />
                                </Button>
                            </li>
                        </ul>
                        <Button
                            v-if="!foodSearchQuery && previousFoodEntries.length > recentFoodLimit"
                            type="button"
                            variant="ghost"
                            class="mt-1 min-h-11 px-2 text-link"
                            aria-controls="add-food-results"
                            :aria-expanded="showAllRecentFoods"
                            @click="showAllRecentFoods = !showAllRecentFoods"
                        >
                            {{ showAllRecentFoods ? 'Show fewer recent foods' : 'Show more recent foods' }}
                        </Button>
                    </div>

                    <div v-else-if="foodSearchQuery.length >= 2" class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-muted p-3">
                        <p class="text-sm text-muted-foreground" role="status">No foods found.</p>
                        <Button :as="Link" :href="addModeUrl('custom')" size="sm" variant="outline">Enter custom food</Button>
                    </div>

                    <p v-else-if="foodSearchQuery.length === 1" class="text-sm text-muted-foreground">Enter one more character to search.</p>
                </Card>

                <QuickMeal v-if="reminderSuggestion" :key="`${date}-${reminderSuggestion.meal_type}-${reminderSuggestion.id}`" :date="date" :meal="reminderSuggestion" />
            </div>

            <Card class="gap-2 px-4 py-4 sm:px-5">
                <h2 class="text-lg font-semibold tracking-tight">More ways to log</h2>
                <div class="grid grid-cols-2 gap-x-2 gap-y-1 md:grid-cols-1">
                    <Button
                        type="button"
                        variant="ghost"
                        class="h-auto min-h-12 min-w-0 justify-start gap-2 px-0.5 py-1.5 text-left whitespace-normal"
                        :disabled="scannerStarting || labelLoading"
                        @click="startScan"
                    >
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-link/10 text-link" aria-hidden="true"><ScanBarcode :size="17" /></span>
                        Scan barcode
                    </Button>
                    <template v-for="method in loggingLinks" :key="method.mode">
                        <Button
                            v-if="method.mode === 'label'"
                            ref="labelScanButtons"
                            :as="subscriptionActive ? 'button' : Link"
                            :type="subscriptionActive ? 'button' : undefined"
                            :href="subscriptionActive ? undefined : addModeUrl('label')"
                            :variant="lookupError ? 'secondary' : 'ghost'"
                            class="h-auto min-h-12 min-w-0 justify-start gap-2 px-0.5 py-1.5 text-left whitespace-normal"
                            :disabled="subscriptionActive && (lookupLoading || scannerStarting)"
                            :loading="subscriptionActive && labelLoading"
                            loading-label="Reading label…"
                            @click="openLabelCamera"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-secondary text-secondary-foreground" aria-hidden="true"><FileText :size="17" /></span>
                            <span class="min-w-0">
                                Scan label
                                <span v-if="!subscriptionActive" class="block text-xs text-muted-foreground">Buff+ required</span>
                            </span>
                        </Button>
                        <Button
                            v-else
                            :as="Link"
                            :href="addModeUrl(method.mode)"
                            variant="ghost"
                            class="h-auto min-h-12 min-w-0 justify-start gap-2 px-0.5 py-1.5 text-left whitespace-normal"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="method.tone" aria-hidden="true">
                                <component :is="method.icon" :size="17" />
                            </span>
                            <span class="min-w-0">
                                {{ method.label }}
                                <span v-if="method.mode === 'photo' && !subscriptionActive" class="block text-xs text-muted-foreground">Buff+ required</span>
                            </span>
                        </Button>
                    </template>
                </div>
                <p v-if="nativeMessage" class="rounded-xl bg-muted p-3 text-sm text-foreground/80">{{ nativeMessage }}</p>
                <p v-if="lookupError && !webScannerOpen" class="rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert">{{ lookupError }}</p>
                <span class="sr-only" role="status">{{ labelLoading ? 'Reading nutrition label…' : '' }}</span>
                <p v-if="labelError" class="rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert">{{ labelError }}</p>
                <Button v-if="labelError && subscriptionRequired" :as="Link" href="/settings/subscription" variant="surface">View Buff+</Button>
            </Card>
        </div>

        <AppSheet
            :open="foodAddSheetOpen"
            labelled-by="food-add-title"
            title="Add food"
            description="Review this food before adding it to your day."
            variant="drawer"
            class="max-h-[88vh] px-6 pt-6"
            :dismissible="!previousMealForm.processing"
            @close="closeFoodAddSheet"
        >
            <Button variant="ghost" size="icon" class="absolute right-4 top-4 h-10 w-10 shrink-0" aria-label="Close add food" :disabled="previousMealForm.processing" @click="closeFoodAddSheet">
                <X :size="24" />
            </Button>

            <div v-if="selectedPreviousMeal || product" class="space-y-5">
                <div class="flex gap-4">
                    <FoodThumbnail
                        :src="(selectedPreviousMeal || product)?.image_url"
                        :icon="selectedPreviousMeal ? 'utensils' : 'barcode'"
                        class="size-20"
                        :icon-size="26"
                    />
                    <div class="min-w-0 flex-1">
                        <p id="food-add-title" class="truncate text-lg font-semibold text-foreground">{{ selectedPreviousMeal?.name || product?.name || 'Add food' }}</p>
                        <p class="truncate text-base text-muted-foreground">{{ selectedPreviousMeal?.brand || product?.brand || (selectedPreviousMeal ? 'Previous item' : 'Saved product') }}</p>
                        <p v-if="selectedPreviousMeal" class="mt-1 text-sm text-foreground">
                            {{ selectedPreviousMeal.calories }} kcal<span v-if="selectedPreviousMeal.portion_quantity"> · {{ previousMealPortionLabel(selectedPreviousMeal) }}</span> · P {{ formatMacro(Number(selectedPreviousMeal.protein_g)) }}g · C {{ formatMacro(Number(selectedPreviousMeal.carbs_g)) }}g · F {{ formatMacro(Number(selectedPreviousMeal.fat_g)) }}g
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
                        :aria-pressed="selectedPortionKey === String(index)"
                        @click="selectPortion(option, index)"
                        >
                            {{ portionOptionLabel(option) }}
                        </Button>
                    </div>

                    <div class="flex min-w-0 gap-2">
                        <Input
                            v-model.number="activeFoodPortionQuantity"
                            type="number"
                            min="0.1"
                            step="any"
                            class="min-w-0 flex-1 py-2.5 text-lg"
                            aria-label="Portion quantity"
                            :aria-invalid="selectedPreviousMeal ? Boolean(previousMealForm.errors.portion_quantity) : undefined"
                            :aria-describedby="selectedPreviousMeal && previousMealForm.errors.portion_quantity ? 'previous-meal-portion-error' : undefined"
                            @input="selectedPortionKey = ''; previousMealForm.clearErrors('portion_quantity')"
                        />
                        <Button
                            v-if="selectedPortion"
                            type="button"
                            variant="surface"
                            class="shrink-0 px-3 text-lg"
                            :aria-label="`Add ${portionOptionLabel(selectedPortion)}`"
                            :disabled="Number(activeFoodPortionQuantity) + selectedPortion.quantity > 10000"
                            @click="addSelectedPortion"
                        >
                            +1
                        </Button>
                    </div>
                    <span v-if="selectedPreviousMeal && selectedPreviousMeal.portion_unit === null" class="self-center text-sm text-muted-foreground">servings</span>
                    <Select v-else v-model="activeFoodPortionUnit">
                        <SelectTrigger class="py-2.5 text-lg" aria-label="Portion unit">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="activeFoodUnit">{{ activeFoodUnit }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <p v-if="selectedPreviousMeal && previousMealForm.errors.portion_quantity" id="previous-meal-portion-error" class="text-sm text-destructive" role="alert">
                    {{ previousMealForm.errors.portion_quantity }}
                </p>
                <p v-else-if="selectedPreviousMeal && previousMealForm.hasErrors" class="text-sm text-destructive" role="alert">
                    {{ Object.values(previousMealForm.errors)[0] }}
                </p>
                <p v-else-if="!selectedPreviousMeal && Object.values(barcodeMealForm.errors)[0]" class="text-sm text-destructive" role="alert">
                    {{ Object.values(barcodeMealForm.errors)[0] }}
                </p>

                <MealTypePicker v-model="selectedMealType" :meal-types="mealTypes" @update:model-value="setMealType" />

                <MacroSummary :macros="activeFoodMacros" />

                <Button
                    class="w-full text-lg"
                    size="lg"
                    :loading="selectedPreviousMeal ? previousMealForm.processing : barcodeMealForm.processing"
                    loading-label="Adding meal…"
                    @click="selectedPreviousMeal ? addPreviousMeal() : addBarcodeMeal()"
                >
                    <Plus :size="18" />
                    Add {{ activeFoodCalories }} kcal
                </Button>
            </div>
        </AppSheet>

        <Card v-if="mode === 'custom' || analysisContext || nutritionLabel">
            <div class="flex items-center gap-2">
                <Utensils :size="21" class="text-food" />
                <h2 id="custom-meal-heading" ref="mealReviewHeading" tabindex="-1" class="card-title">
                    {{ nutritionLabel ? 'Review nutrition label' : analysisContext ? 'Review meal estimate' : 'Custom food' }}
                </h2>
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

            <details v-if="previousCustomMeals.length && !analysisContext && !nutritionLabel" ref="previousCustomFoodList" class="mt-4 rounded-xl border border-border p-3">
                <summary class="cursor-pointer py-1 text-sm font-semibold">Previous custom foods</summary>
                <div class="mt-2 grid gap-2">
                    <Button
                        v-for="meal in previousCustomMeals"
                        :key="meal.id"
                        type="button"
                        variant="surface"
                        class="h-auto w-full min-w-0 justify-start overflow-hidden p-3 text-left"
                        @click="selectPreviousCustomMeal(meal)"
                    >
                        <span class="min-w-0 flex-1 overflow-hidden">
                            <span class="block truncate font-semibold">{{ meal.name }}</span>
                            <span class="mt-0.5 block text-sm font-medium whitespace-normal text-muted-foreground">
                                {{ meal.calories }} kcal<span v-if="meal.portion_quantity"> · {{ previousMealPortionLabel(meal) }}</span> · P {{ meal.protein_g }}g · C {{ meal.carbs_g }}g · F {{ meal.fat_g }}g
                            </span>
                        </span>
                    </Button>
                </div>
            </details>

            <form class="mt-4 space-y-4" aria-labelledby="custom-meal-heading" @submit.prevent="addCustomMeal">
                <label class="block">
                    <span class="field-label">Name</span>
                    <Input
                        v-model="customMealForm.name"
                        type="text"
                        required
                        maxlength="120"
                        class="mt-1"
                        @update:model-value="customMealForm.clearErrors('name')"
                    />
                    <span v-if="customMealForm.errors.name" class="mt-1 block text-sm text-destructive">{{ customMealForm.errors.name }}</span>
                </label>

                <div class="grid grid-cols-[minmax(0,1fr)_4.5rem] gap-2">
                    <label>
                        <span class="field-label">{{ nutritionLabel ? 'Portion eaten' : 'Portion' }}</span>
                        <Input
                            v-model.number="customMealForm.portion_quantity"
                            type="number"
                            min="0.1"
                            :step="nutritionLabel ? 'any' : '0.1'"
                            :required="Boolean(nutritionLabel)"
                            :aria-describedby="nutritionLabel ? 'label-portion-unit' : undefined"
                            class="mt-1 text-right font-semibold"
                            @update:model-value="customMealForm.clearErrors('portion_quantity')"
                        />
                        <span v-if="customMealForm.errors.portion_quantity" class="mt-1 block text-sm text-destructive">{{ customMealForm.errors.portion_quantity }}</span>
                    </label>

                    <div v-if="nutritionLabel" id="label-portion-unit" class="self-end pb-3 text-sm text-muted-foreground">
                        {{ nutritionLabel.portion_unit === 'serving' ? 'servings' : nutritionLabel.portion_unit }}
                    </div>
                    <label v-else>
                        <span class="field-label">Unit</span>
                        <Select v-model="customMealForm.portion_unit" class="mt-1" @update:model-value="customMealForm.clearErrors('portion_unit')">
                            <SelectTrigger class="px-2 font-semibold">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="g">g</SelectItem>
                                <SelectItem value="ml">ml</SelectItem>
                                <SelectItem value="serving">servings</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="customMealForm.errors.portion_unit" class="mt-1 block text-sm text-destructive">{{ customMealForm.errors.portion_unit }}</span>
                    </label>
                </div>

                <p v-if="nutritionLabel" class="text-sm text-muted-foreground">
                    Check the nutrition per {{ nutritionLabel.portion_quantity }} {{ nutritionLabel.portion_unit }} against the label.
                </p>

                <label v-if="nutritionLabel || customMealForm.calories !== null" for="custom-meal-calories" class="block">
                    <span class="field-label">Energy (kcal)</span>
                    <Input
                        id="custom-meal-calories"
                        v-model.number="customMealForm.calories"
                        name="calories"
                        type="number"
                        min="0"
                        max="20000"
                        :step="nutritionLabel ? 'any' : '1'"
                        :required="Boolean(nutritionLabel)"
                        :aria-invalid="Boolean(customMealForm.errors.calories)"
                        :aria-describedby="customMealForm.errors.calories ? 'custom-meal-calories-error' : undefined"
                        class="mt-1 text-right font-semibold"
                        @update:model-value="customMealForm.clearErrors('calories')"
                    />
                    <span v-if="customMealForm.errors.calories" id="custom-meal-calories-error" class="mt-1 block text-sm text-destructive" role="alert">{{ customMealForm.errors.calories }}</span>
                </label>

                <div class="grid grid-cols-3 gap-2">
                    <label v-for="field in customMealMacroFields" :key="field[0]">
                        <span class="field-label">{{ field[1] }}<template v-if="nutritionLabel"> (g)</template></span>
                        <Input
                            v-model.number="customMealForm[field[0]]"
                            type="number"
                            min="0"
                            :step="nutritionLabel ? 'any' : '0.1'"
                            :required="Boolean(nutritionLabel)"
                            placeholder="0"
                            class="mt-1 px-2 text-right font-semibold"
                            @update:model-value="customMealForm.clearErrors(field[0])"
                        />
                        <span v-if="customMealForm.errors[field[0]]" class="mt-1 block text-sm text-destructive" role="alert">{{ customMealForm.errors[field[0]] }}</span>
                    </label>
                </div>

                <MealTypePicker v-model="selectedMealType" :meal-types="mealTypes" @update:model-value="setMealType" />

                <div :class="analysisContext || nutritionLabel ? 'grid grid-cols-2 gap-2' : ''">
                    <Button v-if="analysisContext || nutritionLabel" type="button" variant="surface" :disabled="customMealForm.processing" @click="nutritionLabel ? cancelNutritionLabel() : cancelAnalysis()">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        class="w-full"
                        :loading="customMealForm.processing"
                        :loading-label="analysisContext ? 'Saving meal…' : 'Adding meal…'"
                    >
                        {{ customMealForm.name.trim() ? `${analysisContext ? 'Save' : 'Add'} ${customCalories} kcal` : 'Add food' }}
                    </Button>
                </div>
            </form>
        </Card>

        <AppSheet
            :open="analysisFollowUpOpen"
            labelled-by="analysis-follow-up-title"
            title="Follow up on estimate"
            description="Tell Buff what needs correcting in the estimate."
            @close="closeFollowUpModal"
        >
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
                <Button v-if="subscriptionRequired" :as="Link" href="/settings/subscription" variant="surface" class="w-full">View Buff+</Button>
                <Button
                    class="w-full"
                    :disabled="!analysisFollowUp.trim()"
                    :loading="analysisFollowUpLoading"
                    loading-label="Updating estimate…"
                >
                    Update estimate
                </Button>
            </form>
        </AppSheet>

        <Card v-if="mode === 'workout'">
            <div class="flex items-center gap-2">
                <Dumbbell :size="21" class="text-workout" />
                <h2 class="card-title">Workout</h2>
            </div>

            <WorkoutEntryForm :date="props.date" class="mt-4" />
        </Card>
    </section>
</template>

<style scoped>
.vision-status {
    color: transparent;
    background: linear-gradient(100deg, var(--brand-acid) 30%, var(--brand-white) 48%, var(--brand-acid) 66%);
    background-size: 220% 100%;
    background-clip: text;
    animation: vision-sheen 2.4s linear infinite;
}

.vision-grid {
    background-image:
        linear-gradient(rgb(182 255 81 / 0.09) 1px, transparent 1px),
        linear-gradient(90deg, rgb(182 255 81 / 0.09) 1px, transparent 1px);
    background-size: 32px 32px;
}

.vision-scan-line {
    top: 0;
    height: 2px;
    background: var(--brand-acid);
    box-shadow: 0 0 16px 3px rgb(182 255 81 / 0.65);
    animation: vision-scan 2.4s var(--ease-in-out) infinite alternate;
}

.vision-progress {
    width: 42%;
    animation: vision-progress 2.4s var(--ease-in-out) infinite alternate;
}

@keyframes vision-sheen {
    to {
        background-position: -220% 0;
    }
}

@keyframes vision-scan {
    to {
        top: calc(100% - 2px);
    }
}

@keyframes vision-progress {
    to {
        width: 76%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .vision-status,
    .vision-scan-line,
    .vision-progress {
        animation: none;
    }

    .vision-scan-line {
        top: 50%;
    }

    .vision-progress {
        width: 70%;
    }
}
</style>
