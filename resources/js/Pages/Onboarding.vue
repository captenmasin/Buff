<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowLeft, ArrowRight, Check } from '@lucide/vue';
import Card from '../Components/Card.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';
import SelectContent from '../Components/ui/select/SelectContent.vue';
import SelectItem from '../Components/ui/select/SelectItem.vue';
import SelectTrigger from '../Components/ui/select/SelectTrigger.vue';
import SelectValue from '../Components/ui/select/SelectValue.vue';
import { heightFromCm, heightToCm, weightFromKg, weightToKg, type HeightUnit, type WeightUnit } from '../bodyUnits';

defineOptions({ layout: null });

const props = defineProps<{
    defaults: {
        calories: number;
        protein_g: number;
        carbs_g: number;
        fat_g: number;
        height_cm: number | null;
        target_weight_kg: number | null;
        target_body_fat_percent: number | null;
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
    };
}>();

const page = usePage<{ flash?: { message?: string } }>();
const step = ref(0);
const weightDisplay = ref('');
const heightDisplay = ref('');
const form = useForm({
    calories: props.defaults.calories,
    protein_g: props.defaults.protein_g,
    carbs_g: props.defaults.carbs_g,
    fat_g: props.defaults.fat_g,
    height_cm: props.defaults.height_cm ?? '',
    target_weight_kg: props.defaults.target_weight_kg ?? '',
    target_body_fat_percent: props.defaults.target_body_fat_percent ?? '',
    weight_unit: props.defaults.weight_unit,
    height_unit: props.defaults.height_unit,
});

const steps = ['Daily Targets', 'Body & Units'];
const currentStep = computed(() => steps[step.value]);
const macroCalories = computed(() => Math.round((Number(form.protein_g) * 4) + (Number(form.carbs_g) * 4) + (Number(form.fat_g) * 9)));
const macrosMatch = computed(() => macroCalories.value === Number(form.calories));

function syncDisplayFromStored() {
    weightDisplay.value = form.target_weight_kg === '' ? '' : String(weightFromKg(Number(form.target_weight_kg), form.weight_unit));
    heightDisplay.value = form.height_cm === '' ? '' : String(heightFromCm(Number(form.height_cm), form.height_unit));
}

function syncStoredFromDisplay(weightUnit: WeightUnit = form.weight_unit, heightUnit: HeightUnit = form.height_unit) {
    form.target_weight_kg = weightToKg(weightDisplay.value, weightUnit);
    form.height_cm = heightToCm(heightDisplay.value, heightUnit);
}

function nextStep() {
    syncStoredFromDisplay();

    if (currentStep.value === 'Daily Targets' && !macrosMatch.value) {
        return;
    }

    if (step.value < steps.length - 1) {
        step.value += 1;
    }
}

function previousStep() {
    if (step.value > 0) {
        step.value -= 1;
    }
}

function finish() {
    syncStoredFromDisplay();
    form.post('/onboarding', {
        onError: (errors) => {
            if (errors.calories || errors.protein_g || errors.carbs_g || errors.fat_g) {
                step.value = 0;
            }
        },
    });
}

watch(
    () => [form.weight_unit, form.height_unit] as const,
    (_currentUnits, [previousWeightUnit, previousHeightUnit]) => {
        syncStoredFromDisplay(previousWeightUnit, previousHeightUnit);
        syncDisplayFromStored();
    },
);
syncDisplayFromStored();
</script>

<template>
    <Head title="Set up Buff" />

    <main class="min-h-dvh bg-background px-4 pb-[calc(env(safe-area-inset-bottom,0px)+2.5rem)] pt-[calc(env(safe-area-inset-top,0px)+2.5rem)] text-foreground">
    <form class="mx-auto max-w-md space-y-5" @submit.prevent="currentStep === 'Body & Units' ? finish() : nextStep()">
        <PageHeader>Set up Buff</PageHeader>

        <p v-if="page.props.flash?.message" class="rounded-xl bg-secondary px-4 py-3 text-sm" role="status">{{ page.props.flash.message }}</p>

        <div class="grid grid-cols-2 gap-2">
            <div
                v-for="(label, index) in steps"
                :key="label"
                class="rounded-xl border px-3 py-2 text-center text-sm font-semibold"
                :class="index === step ? 'border-primary bg-secondary text-foreground' : 'border-border text-muted-foreground'"
            >
                {{ label }}
            </div>
        </div>

        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div :key="currentStep" class="space-y-5">
        <Card v-if="currentStep === 'Body & Units'">
            <div class="space-y-3">
                <h2 class="font-semibold">Body & units</h2>
                <label class="block">
                    <span class="field-label">Weight</span>
                    <Select v-model="form.weight_unit" class="mt-1">
                        <SelectTrigger>
                            <SelectValue placeholder="Select weight unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="kg">Kilograms</SelectItem>
                            <SelectItem value="lb">Pounds</SelectItem>
                        </SelectContent>
                    </Select>
                </label>
                <label class="block">
                    <span class="field-label">Height</span>
                    <Select v-model="form.height_unit" class="mt-1">
                        <SelectTrigger>
                            <SelectValue placeholder="Select height unit" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="cm">Centimeters</SelectItem>
                            <SelectItem value="in">Inches</SelectItem>
                        </SelectContent>
                    </Select>
                </label>
            </div>
        </Card>

        <Card v-if="currentStep === 'Daily Targets'">
            <div class="space-y-3">
                <h2 class="font-semibold">Daily targets</h2>
                <label class="block">
                    <span class="field-label">Calories</span>
                    <Input v-model.number="form.calories" type="number" min="1" class="mt-1" />
                    <span v-if="form.errors.calories" class="mt-1 block text-sm text-destructive">{{ form.errors.calories }}</span>
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <label>
                        <span class="field-label">Protein</span>
                        <Input v-model.number="form.protein_g" type="number" min="0" step="0.1" class="mt-1" />
                        <span v-if="form.errors.protein_g" class="mt-1 block text-sm text-destructive">{{ form.errors.protein_g }}</span>
                    </label>
                    <label>
                        <span class="field-label">Carbs</span>
                        <Input v-model.number="form.carbs_g" type="number" min="0" step="0.1" class="mt-1" />
                        <span v-if="form.errors.carbs_g" class="mt-1 block text-sm text-destructive">{{ form.errors.carbs_g }}</span>
                    </label>
                    <label>
                        <span class="field-label">Fat</span>
                        <Input v-model.number="form.fat_g" type="number" min="0" step="0.1" class="mt-1" />
                        <span v-if="form.errors.fat_g" class="mt-1 block text-sm text-destructive">{{ form.errors.fat_g }}</span>
                    </label>
                </div>
                <p class="rounded-xl p-3 text-sm" :class="macrosMatch ? 'bg-success-soft text-success-soft-foreground' : 'bg-danger-soft text-danger-soft-foreground'">
                    {{ macroCalories }} kcal from macros
                </p>
            </div>
        </Card>

        <Card v-if="currentStep === 'Body & Units'">
            <div class="space-y-3">
                <h2 class="font-semibold">Body profile</h2>
                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="field-label">Height {{ form.height_unit }}</span>
                        <Input v-model="heightDisplay" type="number" min="1" step="0.1" class="mt-1" />
                        <span v-if="form.errors.height_cm" class="mt-1 block text-sm text-destructive">{{ form.errors.height_cm }}</span>
                    </label>
                    <label>
                        <span class="field-label">Target {{ form.weight_unit }}</span>
                        <Input v-model="weightDisplay" type="number" min="1" step="0.1" class="mt-1" />
                        <span v-if="form.errors.target_weight_kg" class="mt-1 block text-sm text-destructive">{{ form.errors.target_weight_kg }}</span>
                    </label>
                </div>
                <label class="block">
                    <span class="field-label">Target body fat %</span>
                    <Input v-model.number="form.target_body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" />
                    <span v-if="form.errors.target_body_fat_percent" class="mt-1 block text-sm text-destructive">{{ form.errors.target_body_fat_percent }}</span>
                </label>
            </div>
        </Card>
            </div>
        </Transition>

        <div class="grid grid-cols-2 gap-2">
            <Button type="button" variant="surface" :disabled="step === 0" @click="previousStep">
                <ArrowLeft :size="18" />
                Back
            </Button>
            <Button
                v-if="step < steps.length - 1"
                type="submit"
                :disabled="currentStep === 'Daily Targets' && !macrosMatch"
            >
                Next
                <ArrowRight :size="18" />
            </Button>
            <Button v-else type="submit" :disabled="form.processing">
                <Check :size="18" />
                Start
            </Button>
        </div>
    </form>
    </main>
</template>
