<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowLeft, ArrowRight, Check } from '@lucide/vue';
import BodyProfileEditor from '../Components/BodyProfileEditor.vue';
import Card from '../Components/Card.vue';
import DailyTargetsEditor from '../Components/DailyTargetsEditor.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';
import SelectContent from '../Components/ui/select/SelectContent.vue';
import SelectItem from '../Components/ui/select/SelectItem.vue';
import SelectTrigger from '../Components/ui/select/SelectTrigger.vue';
import SelectValue from '../Components/ui/select/SelectValue.vue';
import { type ActivityLevel, type Sex } from '../bodyProfile';
import { heightFromCm, heightToCm, weightFromKg, weightToKg, type HeightUnit, type WeightUnit } from '../bodyUnits';

defineOptions({ layout: null });

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

const page = usePage<{ flash?: { message?: string } }>();
const step = ref(0);
const targetsValid = ref(true);
const currentWeightDisplay = ref('');
const targetWeightDisplay = ref('');
const heightDisplay = ref('');
const form = useForm({
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
});

const steps = ['Daily Targets', 'Body & Units'];
const currentStep = computed(() => steps[step.value]);

function syncDisplayFromStored() {
    currentWeightDisplay.value = form.current_weight_kg === '' ? '' : String(weightFromKg(Number(form.current_weight_kg), form.weight_unit));
    targetWeightDisplay.value = form.target_weight_kg === '' ? '' : String(weightFromKg(Number(form.target_weight_kg), form.weight_unit));
    heightDisplay.value = form.height_cm === '' ? '' : String(heightFromCm(Number(form.height_cm), form.height_unit));
}

function syncStoredFromDisplay(weightUnit: WeightUnit = form.weight_unit, heightUnit: HeightUnit = form.height_unit) {
    form.current_weight_kg = weightToKg(currentWeightDisplay.value, weightUnit);
    form.target_weight_kg = weightToKg(targetWeightDisplay.value, weightUnit);
    form.height_cm = heightToCm(heightDisplay.value, heightUnit);
}

function nextStep() {
    syncStoredFromDisplay();

    if (currentStep.value === 'Daily Targets' && !targetsValid.value) {
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
                <h2 class="card-title">Body & units</h2>
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
                            <SelectItem value="in">Feet and inches</SelectItem>
                        </SelectContent>
                    </Select>
                </label>
            </div>
        </Card>

        <DailyTargetsEditor
            v-if="currentStep === 'Daily Targets'"
            v-model:calories="form.calories"
            v-model:protein_g="form.protein_g"
            v-model:carbs_g="form.carbs_g"
            v-model:fat_g="form.fat_g"
            :errors="form.errors"
            @valid="targetsValid = $event"
        />

        <Card v-if="currentStep === 'Body & Units'">
            <div class="space-y-3">
                <h2 class="card-title">Body profile</h2>
                <BodyProfileEditor
                    v-model:age="form.age"
                    v-model:sex="form.sex"
                    v-model:height="heightDisplay"
                    v-model:activity_level="form.activity_level"
                    :height-unit="form.height_unit"
                    :errors="form.errors"
                />
            </div>
        </Card>

        <Card v-if="currentStep === 'Body & Units'">
            <div class="space-y-3">
                <h2 class="card-title">Body goals</h2>
                <label class="block">
                    <span class="field-label">Current {{ form.weight_unit }}</span>
                    <Input v-model="currentWeightDisplay" type="number" min="1" step="0.1" class="mt-1" />
                    <span v-if="form.errors.current_weight_kg" class="mt-1 block text-sm text-destructive">{{ form.errors.current_weight_kg }}</span>
                </label>
                <label class="block">
                    <span class="field-label">Target {{ form.weight_unit }}</span>
                    <Input v-model="targetWeightDisplay" type="number" min="1" step="0.1" class="mt-1" />
                    <span v-if="form.errors.target_weight_kg" class="mt-1 block text-sm text-destructive">{{ form.errors.target_weight_kg }}</span>
                </label>
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
                :disabled="currentStep === 'Daily Targets' && !targetsValid"
            >
                Next
                <ArrowRight :size="18" />
            </Button>
            <Button v-else type="submit" :disabled="form.processing || currentWeightDisplay === ''">
                <Check :size="18" />
                Start
            </Button>
        </div>
    </form>
    </main>
</template>
