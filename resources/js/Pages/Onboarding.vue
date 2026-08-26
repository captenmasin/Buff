<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import Card from '../Components/Card.vue';
import DailyTargetsEditor from '../Components/DailyTargetsEditor.vue';
import OfflineBanner from '../Components/OfflineBanner.vue';
import SetupFlow from '../Components/SetupFlow.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { activityLevelOptions, sexOptions, type ActivityLevel, type Sex } from '../bodyProfile';
import {
    feetAndInchesFromInches,
    heightFromCm,
    heightToCm,
    inchesFromFeetAndInches,
    weightFromKg,
    weightToKg,
    type HeightUnit,
    type WeightUnit,
} from '../bodyUnits';

defineOptions({ layout: null });

type Goal = 'lose' | 'maintain' | 'gain';
type SetupStep = 'age' | 'sex' | 'height' | 'current_weight' | 'activity' | 'goal' | 'target_weight' | 'pace' | 'body_fat' | 'plan';
type NumericInput = number | string;

interface OnboardingData {
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    height_cm: NumericInput;
    age: NumericInput;
    sex: Sex | '';
    activity_level: ActivityLevel | '';
    current_weight_kg: NumericInput;
    target_weight_kg: NumericInput;
    target_body_fat_percent: NumericInput;
    weight_unit: WeightUnit;
    height_unit: HeightUnit;
    goal: Goal | '';
    weekly_goal_kg: NumericInput;
}

interface SavedDraft {
    data: Partial<OnboardingData>;
    step: SetupStep;
    customizingPlan?: boolean;
    plan?: PlanResponse | null;
}

interface PlanResponse {
    calories: number;
    protein_g: number;
    carbs_g: number;
    fat_g: number;
    macro_calories: number;
    maintenance_calories: number | null;
    personalized: boolean;
    teen_maintenance_only: boolean;
    notice: string;
}

const props = defineProps<{
    defaults: {
        calories: number;
        protein_g: number;
        carbs_g: number;
        fat_g: number;
        height_cm: number | null;
        age: number | null;
        sex: Sex | null;
        activity_level: ActivityLevel | null;
        current_weight_kg: number | null;
        target_weight_kg: number | null;
        target_body_fat_percent: number | null;
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
    };
}>();

const page = usePage<{ buff?: { account?: { id?: string } | null } }>();
const allSteps: SetupStep[] = ['age', 'sex', 'height', 'current_weight', 'activity', 'goal', 'target_weight', 'pace', 'body_fat', 'plan'];
const draftKey = `buff:onboarding-draft:${page.props.buff?.account?.id ?? 'local'}`;

function readDraft(): SavedDraft | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const saved = JSON.parse(window.localStorage.getItem(draftKey) ?? 'null') as SavedDraft | null;

        return saved && allSteps.includes(saved.step) && saved.data ? saved : null;
    } catch {
        return null;
    }
}

const savedDraft = readDraft();
const form = useForm<OnboardingData>({
    calories: props.defaults.calories,
    protein_g: props.defaults.protein_g,
    carbs_g: props.defaults.carbs_g,
    fat_g: props.defaults.fat_g,
    height_cm: props.defaults.height_cm ?? '',
    age: props.defaults.age ?? '',
    sex: props.defaults.sex ?? '',
    activity_level: props.defaults.activity_level ?? '',
    current_weight_kg: props.defaults.current_weight_kg ?? '',
    target_weight_kg: props.defaults.target_weight_kg ?? '',
    target_body_fat_percent: props.defaults.target_body_fat_percent ?? '',
    weight_unit: props.defaults.weight_unit,
    height_unit: props.defaults.height_unit,
    goal: '',
    weekly_goal_kg: '',
    ...savedDraft?.data,
});
const currentStep = ref<SetupStep>(savedDraft?.step ?? 'age');
const targetsValid = ref(true);
const customizingPlan = ref(savedDraft?.customizingPlan ?? false);
const plan = ref<PlanResponse | null>(savedDraft?.plan ?? null);
const planLoading = ref(false);
const planError = ref('');
const heightDisplay = ref<NumericInput>(form.height_cm === '' ? '' : (heightFromCm(Number(form.height_cm), form.height_unit) ?? ''));
const initialImperialHeight = feetAndInchesFromInches(form.height_cm === '' ? '' : heightFromCm(Number(form.height_cm), 'in'));
const heightFeet = ref<NumericInput>(initialImperialHeight.feet);
const heightInches = ref<NumericInput>(initialImperialHeight.inches);
const currentWeightDisplay = ref<NumericInput>(form.current_weight_kg === '' ? '' : (weightFromKg(Number(form.current_weight_kg), form.weight_unit) ?? ''));
const targetWeightDisplay = ref<NumericInput>(form.target_weight_kg === '' ? '' : (weightFromKg(Number(form.target_weight_kg), form.weight_unit) ?? ''));

const isTeen = computed(() => form.age !== '' && Number(form.age) >= 13 && Number(form.age) < 18);
const activeSteps = computed<SetupStep[]>(() => {
    const steps: SetupStep[] = ['age', 'sex', 'height', 'current_weight', 'activity', 'goal'];

    if (!isTeen.value && (form.goal === 'lose' || form.goal === 'gain')) {
        steps.push('target_weight', 'pace');
    }

    return [...steps, 'body_fat', 'plan'];
});
const currentStepIndex = computed(() => Math.max(0, activeSteps.value.indexOf(currentStep.value)));
const progress = computed(() => ((4 + currentStepIndex.value + 1) / (4 + activeSteps.value.length)) * 100);
const phase = computed(() => {
    if (['age', 'sex', 'height', 'current_weight', 'activity'].includes(currentStep.value)) {
        return 'About you';
    }

    return currentStep.value === 'plan' ? 'Your plan' : 'Your goal';
});
const nextLabel = computed(() => currentStep.value === 'plan' ? 'Start Buff' : 'Next');
const selectedTargetIsValid = computed(() => {
    if (form.target_weight_kg === '' || form.current_weight_kg === '') {
        return false;
    }

    return form.goal === 'lose'
        ? Number(form.target_weight_kg) < Number(form.current_weight_kg)
        : Number(form.target_weight_kg) > Number(form.current_weight_kg);
});
const nextDisabled = computed(() => {
    if (currentStep.value === 'age') {
        return form.age !== '' && (Number(form.age) < 13 || Number(form.age) > 120);
    }

    if (currentStep.value === 'height') {
        return form.height_cm !== '' && (Number(form.height_cm) < 50 || Number(form.height_cm) > 260);
    }

    if (currentStep.value === 'current_weight') {
        return form.current_weight_kg === '' || Number(form.current_weight_kg) < 1 || Number(form.current_weight_kg) > 1000;
    }

    if (currentStep.value === 'goal') {
        return form.goal === '';
    }

    if (currentStep.value === 'target_weight') {
        return !selectedTargetIsValid.value;
    }

    if (currentStep.value === 'pace') {
        return form.weekly_goal_kg === '';
    }

    if (currentStep.value === 'body_fat') {
        return form.target_body_fat_percent !== ''
            && (Number(form.target_body_fat_percent) < 1 || Number(form.target_body_fat_percent) > 80);
    }

    return currentStep.value === 'plan' && (planLoading.value || (customizingPlan.value && !targetsValid.value));
});
const paceOptions = computed(() => {
    const isLoss = form.goal === 'lose';

    if (form.weight_unit === 'lb') {
        const pounds = isLoss ? [0.5, 1, 1.5] : [0.25, 0.5, 1];

        return pounds.map((value, index) => ({
            value: Number((value / 2.2046226218).toFixed(3)),
            title: ['Gentle', 'Steady', 'Faster'][index],
            label: `${value} lb per week`,
        }));
    }

    const kilograms = isLoss ? [0.25, 0.5, 0.75] : [0.1, 0.25, 0.5];

    return kilograms.map((value, index) => ({
        value,
        title: ['Gentle', 'Steady', 'Faster'][index],
        label: `${value} kg per week`,
    }));
});

function optionClasses(selected: boolean): string {
    return selected
        ? 'border-brand-violet bg-brand-violet/10 ring-1 ring-brand-violet'
        : 'border-border bg-card hover:bg-muted';
}

function setHeightUnit(unit: HeightUnit): void {
    form.height_unit = unit;
    heightDisplay.value = form.height_cm === '' ? '' : (heightFromCm(Number(form.height_cm), unit) ?? '');

    if (unit === 'in') {
        const imperial = feetAndInchesFromInches(heightDisplay.value);
        heightFeet.value = imperial.feet;
        heightInches.value = imperial.inches;
    }
}

function setWeightUnit(unit: WeightUnit): void {
    form.weight_unit = unit;
    currentWeightDisplay.value = form.current_weight_kg === '' ? '' : (weightFromKg(Number(form.current_weight_kg), unit) ?? '');
    targetWeightDisplay.value = form.target_weight_kg === '' ? '' : (weightFromKg(Number(form.target_weight_kg), unit) ?? '');
}

function selectGoal(goal: Goal): void {
    form.goal = goal;

    if (goal === 'maintain' || isTeen.value) {
        form.target_weight_kg = '';
        targetWeightDisplay.value = '';
        form.weekly_goal_kg = '';
    }
}

async function loadPlan(): Promise<void> {
    planLoading.value = true;
    planError.value = '';

    try {
        const response = await axios.post<PlanResponse>('/onboarding/plan', {
            age: form.age,
            sex: form.sex,
            height_cm: form.height_cm,
            activity_level: form.activity_level,
            current_weight_kg: form.current_weight_kg,
            goal: form.goal,
            weekly_goal_kg: form.weekly_goal_kg,
        });

        plan.value = response.data;
        form.calories = response.data.calories;
        form.protein_g = response.data.protein_g;
        form.carbs_g = response.data.carbs_g;
        form.fat_g = response.data.fat_g;
    } catch {
        plan.value = null;
        planError.value = 'Buff could not refresh the estimate, so the editable defaults are shown.';
    } finally {
        planLoading.value = false;
    }
}

async function nextStep(): Promise<void> {
    if (nextDisabled.value) {
        return;
    }

    if (currentStep.value === 'plan') {
        form.post('/onboarding', {
            onSuccess: () => {
                window.localStorage.removeItem(draftKey);
                window.localStorage.removeItem('buff:registration-name');
            },
            onError: (errors) => {
                if (errors.calories || errors.protein_g || errors.carbs_g || errors.fat_g) {
                    currentStep.value = 'plan';
                    customizingPlan.value = true;
                } else if (errors.current_weight_kg) {
                    currentStep.value = 'current_weight';
                } else if (errors.height_cm || errors.age || errors.sex || errors.activity_level) {
                    currentStep.value = errors.age ? 'age' : errors.sex ? 'sex' : errors.height_cm ? 'height' : 'activity';
                } else if (errors.target_weight_kg) {
                    currentStep.value = 'target_weight';
                } else if (errors.target_body_fat_percent) {
                    currentStep.value = 'body_fat';
                }
            },
        });

        return;
    }

    const next = activeSteps.value[currentStepIndex.value + 1];

    if (next) {
        currentStep.value = next;

        if (next === 'plan') {
            await loadPlan();
        }
    }
}

function previousStep(): void {
    if (currentStepIndex.value > 0) {
        currentStep.value = activeSteps.value[currentStepIndex.value - 1];
    }
}

watch(heightDisplay, (value) => {
    form.height_cm = heightToCm(value, form.height_unit);
});
watch([heightFeet, heightInches], ([feet, inches]) => {
    if (form.height_unit === 'in') {
        heightDisplay.value = inchesFromFeetAndInches(feet, inches);
    }
});
watch(currentWeightDisplay, (value) => {
    form.current_weight_kg = weightToKg(value, form.weight_unit);
});
watch(targetWeightDisplay, (value) => {
    form.target_weight_kg = weightToKg(value, form.weight_unit);
});
watch(isTeen, (teen) => {
    if (teen) {
        form.target_weight_kg = '';
        targetWeightDisplay.value = '';
        form.weekly_goal_kg = '';
    }
});
watch(activeSteps, (steps) => {
    if (!steps.includes(currentStep.value)) {
        currentStep.value = 'goal';
    }
});
watch([() => form.data(), currentStep, customizingPlan, plan], ([data, step]) => {
    if (typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(draftKey, JSON.stringify({ data, step, customizingPlan: customizingPlan.value, plan: plan.value }));
        } catch {
            // The flow still works without resumable local state.
        }
    }
}, { deep: true });

onMounted(() => {
    window.localStorage.removeItem('buff:registration-name');

    if (currentStep.value === 'plan' && plan.value === null) {
        void loadPlan();
    }
});
</script>

<template>
    <div>
        <Head title="Set up Buff" />
        <OfflineBanner />

        <SetupFlow
            :phase="phase"
            :progress="progress"
            :next-label="nextLabel"
            :next-disabled="nextDisabled"
            :processing="form.processing"
            :back-disabled="currentStepIndex === 0"
            @back="previousStep"
            @next="nextStep"
        >
            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="translate-x-3 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="-translate-x-3 opacity-0"
            >
                <div :key="currentStep" class="space-y-8">
                    <template v-if="currentStep === 'age'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">How old are you?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">This helps estimate your daily energy needs. You can leave it blank.</p>
                        </header>
                        <label class="block">
                            <span class="field-label">Age</span>
                            <Input v-model="form.age" type="number" inputmode="numeric" min="13" max="120" autofocus class="mt-2 h-16 rounded-xl px-4 text-lg" :aria-invalid="Boolean(form.errors.age)" @keyup.enter="nextStep" />
                            <span v-if="form.errors.age" class="mt-2 block text-sm text-destructive">{{ form.errors.age }}</span>
                        </label>
                    </template>

                    <template v-else-if="currentStep === 'sex'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">Which sex should Buff use for your estimate?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">This changes the energy equation. You can leave it unanswered.</p>
                        </header>
                        <div class="grid gap-3" role="radiogroup" aria-label="Sex used for energy estimate">
                            <button v-for="option in sexOptions" :key="option.value" type="button" role="radio" class="min-h-16 rounded-xl border px-4 text-left text-lg font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :class="optionClasses(form.sex === option.value)" :aria-checked="form.sex === option.value" @click="form.sex = option.value">
                                {{ option.label }}
                            </button>
                        </div>
                    </template>

                    <template v-else-if="currentStep === 'height'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">How tall are you?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">This is optional, but it improves your estimate.</p>
                        </header>
                        <div class="grid grid-cols-2 gap-2 rounded-xl bg-muted p-1" role="radiogroup" aria-label="Height unit">
                            <button type="button" role="radio" class="h-11 rounded-lg font-semibold" :class="form.height_unit === 'cm' ? 'bg-card shadow-sm' : 'text-muted-foreground'" :aria-checked="form.height_unit === 'cm'" @click="setHeightUnit('cm')">Centimetres</button>
                            <button type="button" role="radio" class="h-11 rounded-lg font-semibold" :class="form.height_unit === 'in' ? 'bg-card shadow-sm' : 'text-muted-foreground'" :aria-checked="form.height_unit === 'in'" @click="setHeightUnit('in')">Feet & inches</button>
                        </div>
                        <div v-if="form.height_unit === 'in'" class="grid grid-cols-2 gap-3">
                            <label>
                                <span class="field-label">Feet</span>
                                <Input v-model="heightFeet" type="number" inputmode="numeric" min="1" max="8" autofocus class="mt-2 h-16 rounded-xl px-4 text-lg" />
                            </label>
                            <label>
                                <span class="field-label">Inches</span>
                                <Input v-model="heightInches" type="number" inputmode="decimal" min="0" max="11.9" step="0.1" class="mt-2 h-16 rounded-xl px-4 text-lg" @keyup.enter="nextStep" />
                            </label>
                        </div>
                        <label v-else class="block">
                            <span class="field-label">Height in centimetres</span>
                            <Input v-model="heightDisplay" type="number" inputmode="decimal" min="50" max="260" step="0.1" autofocus class="mt-2 h-16 rounded-xl px-4 text-lg" :aria-invalid="Boolean(form.errors.height_cm)" @keyup.enter="nextStep" />
                        </label>
                        <span v-if="form.errors.height_cm" class="block text-sm text-destructive">{{ form.errors.height_cm }}</span>
                    </template>

                    <template v-else-if="currentStep === 'current_weight'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">What’s your current weight?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">Buff uses this as the starting point for progress.</p>
                        </header>
                        <div class="grid grid-cols-2 gap-2 rounded-xl bg-muted p-1" role="radiogroup" aria-label="Weight unit">
                            <button type="button" role="radio" class="h-11 rounded-lg font-semibold" :class="form.weight_unit === 'kg' ? 'bg-card shadow-sm' : 'text-muted-foreground'" :aria-checked="form.weight_unit === 'kg'" @click="setWeightUnit('kg')">Kilograms</button>
                            <button type="button" role="radio" class="h-11 rounded-lg font-semibold" :class="form.weight_unit === 'lb' ? 'bg-card shadow-sm' : 'text-muted-foreground'" :aria-checked="form.weight_unit === 'lb'" @click="setWeightUnit('lb')">Pounds</button>
                        </div>
                        <label class="block">
                            <span class="field-label">Current {{ form.weight_unit }}</span>
                            <Input v-model="currentWeightDisplay" type="number" inputmode="decimal" min="1" step="0.1" autofocus class="mt-2 h-16 rounded-xl px-4 text-lg" :aria-invalid="Boolean(form.errors.current_weight_kg)" @keyup.enter="nextStep" />
                            <span v-if="form.errors.current_weight_kg" class="mt-2 block text-sm text-destructive">{{ form.errors.current_weight_kg }}</span>
                        </label>
                    </template>

                    <template v-else-if="currentStep === 'activity'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">How active is a typical week?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">Choose the closest match, or leave it unanswered.</p>
                        </header>
                        <div class="grid gap-3" role="radiogroup" aria-label="Typical activity">
                            <button v-for="option in activityLevelOptions" :key="option.value" type="button" role="radio" class="rounded-xl border px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :class="optionClasses(form.activity_level === option.value)" :aria-checked="form.activity_level === option.value" @click="form.activity_level = option.value">
                                <span class="block font-semibold">{{ option.label }}</span>
                                <span class="mt-0.5 block text-sm text-muted-foreground">{{ option.description }}</span>
                            </button>
                        </div>
                    </template>

                    <template v-else-if="currentStep === 'goal'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">What would you like to do?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">{{ isTeen ? 'Buff will keep your recommendation at maintenance while you’re still growing.' : 'This sets the direction of your starting plan.' }}</p>
                        </header>
                        <div class="grid gap-3" role="radiogroup" aria-label="Weight goal">
                            <button type="button" role="radio" class="min-h-16 rounded-xl border px-4 text-left text-lg font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :class="optionClasses(form.goal === 'lose')" :aria-checked="form.goal === 'lose'" @click="selectGoal('lose')">Lose weight</button>
                            <button type="button" role="radio" class="min-h-16 rounded-xl border px-4 text-left text-lg font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :class="optionClasses(form.goal === 'maintain')" :aria-checked="form.goal === 'maintain'" @click="selectGoal('maintain')">Maintain weight</button>
                            <button type="button" role="radio" class="min-h-16 rounded-xl border px-4 text-left text-lg font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :class="optionClasses(form.goal === 'gain')" :aria-checked="form.goal === 'gain'" @click="selectGoal('gain')">Gain weight</button>
                        </div>
                    </template>

                    <template v-else-if="currentStep === 'target_weight'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">What’s your target weight?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">Choose a {{ form.goal === 'lose' ? 'lower' : 'higher' }} weight than your current {{ currentWeightDisplay }} {{ form.weight_unit }}.</p>
                        </header>
                        <label class="block">
                            <span class="field-label">Target {{ form.weight_unit }}</span>
                            <Input v-model="targetWeightDisplay" type="number" inputmode="decimal" min="1" step="0.1" autofocus class="mt-2 h-16 rounded-xl px-4 text-lg" :aria-invalid="!selectedTargetIsValid && targetWeightDisplay !== ''" @keyup.enter="nextStep" />
                            <span v-if="targetWeightDisplay !== '' && !selectedTargetIsValid" class="mt-2 block text-sm text-destructive">Target weight must be {{ form.goal === 'lose' ? 'below' : 'above' }} your current weight.</span>
                            <span v-if="form.errors.target_weight_kg" class="mt-2 block text-sm text-destructive">{{ form.errors.target_weight_kg }}</span>
                        </label>
                    </template>

                    <template v-else-if="currentStep === 'pace'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">How quickly would you like to move?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">Buff caps the calorie adjustment at 20% of estimated maintenance.</p>
                        </header>
                        <div class="grid gap-3" role="radiogroup" aria-label="Weekly goal pace">
                            <button v-for="option in paceOptions" :key="option.value" type="button" role="radio" class="rounded-xl border px-4 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :class="optionClasses(Number(form.weekly_goal_kg) === option.value)" :aria-checked="Number(form.weekly_goal_kg) === option.value" @click="form.weekly_goal_kg = option.value">
                                <span class="block font-semibold">{{ option.title }}</span>
                                <span class="mt-0.5 block text-sm text-muted-foreground">{{ option.label }}</span>
                            </button>
                        </div>
                    </template>

                    <template v-else-if="currentStep === 'body_fat'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">Do you have a body-fat target?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">This is optional and does not change the calorie estimate.</p>
                        </header>
                        <label class="block">
                            <span class="field-label">Target body fat %</span>
                            <Input v-model="form.target_body_fat_percent" type="number" inputmode="decimal" min="1" max="80" step="0.1" autofocus class="mt-2 h-16 rounded-xl px-4 text-lg" :aria-invalid="Boolean(form.errors.target_body_fat_percent)" @keyup.enter="nextStep" />
                            <span v-if="form.errors.target_body_fat_percent" class="mt-2 block text-sm text-destructive">{{ form.errors.target_body_fat_percent }}</span>
                        </label>
                    </template>

                    <template v-else>
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">{{ plan?.personalized ? 'Here’s your starting plan' : 'Start with a simple plan' }}</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">You can change this at any time in Goals.</p>
                        </header>
                        <div v-if="planLoading" class="animate-pulse space-y-3" aria-label="Building your plan">
                            <div class="h-32 rounded-xl bg-muted" />
                            <div class="h-20 rounded-xl bg-muted" />
                        </div>
                        <template v-else>
                            <DailyTargetsEditor v-if="customizingPlan" v-model:calories="form.calories" v-model:protein_g="form.protein_g" v-model:carbs_g="form.carbs_g" v-model:fat_g="form.fat_g" :errors="form.errors" @valid="targetsValid = $event" />
                            <template v-else>
                                <Card class="text-center">
                                    <p class="field-label">Daily target</p>
                                    <p class="mt-2 text-5xl font-bold tracking-tight tabular-nums">{{ form.calories.toLocaleString() }}</p>
                                    <p class="mt-1 text-sm text-muted-foreground">kcal per day</p>
                                    <div class="mt-5 grid grid-cols-3 gap-2">
                                        <div class="rounded-xl bg-muted p-3"><p class="field-label">Protein</p><p class="mt-1 font-semibold tabular-nums">{{ Math.round(form.protein_g) }}g</p></div>
                                        <div class="rounded-xl bg-muted p-3"><p class="field-label">Carbs</p><p class="mt-1 font-semibold tabular-nums">{{ Math.round(form.carbs_g) }}g</p></div>
                                        <div class="rounded-xl bg-muted p-3"><p class="field-label">Fat</p><p class="mt-1 font-semibold tabular-nums">{{ Math.round(form.fat_g) }}g</p></div>
                                    </div>
                                </Card>
                                <p class="rounded-xl bg-secondary px-4 py-3 text-sm" role="status">{{ plan?.notice || planError }}</p>
                                <Button type="button" variant="surface" class="w-full" @click="customizingPlan = true">Customize calories and macros</Button>
                            </template>
                        </template>
                    </template>
                </div>
            </Transition>
        </SetupFlow>
    </div>
</template>
