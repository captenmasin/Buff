<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowLeft, ArrowRight, Check } from '@lucide/vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';

const props = defineProps<{
    defaults: {
        calories: number;
        protein_g: number;
        carbs_g: number;
        fat_g: number;
        height_cm: number | null;
        target_weight_kg: number | null;
        target_body_fat_percent: number | null;
        weight_unit: 'kg' | 'lb';
        height_unit: 'cm' | 'in';
    };
}>();

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

const steps = ['Units', 'Goals', 'Body'];
const macroCalories = computed(() => Math.round((Number(form.protein_g) * 4) + (Number(form.carbs_g) * 4) + (Number(form.fat_g) * 9)));
const macrosMatch = computed(() => macroCalories.value === Number(form.calories));

function kgToLb(value: number): number {
    return Number((value * 2.2046226218).toFixed(1));
}

function lbToKg(value: number): number {
    return Number((value / 2.2046226218).toFixed(1));
}

function cmToIn(value: number): number {
    return Number((value / 2.54).toFixed(1));
}

function inToCm(value: number): number {
    return Number((value * 2.54).toFixed(1));
}

function syncDisplayFromStored() {
    weightDisplay.value = form.target_weight_kg === '' ? '' : String(form.weight_unit === 'lb' ? kgToLb(Number(form.target_weight_kg)) : form.target_weight_kg);
    heightDisplay.value = form.height_cm === '' ? '' : String(form.height_unit === 'in' ? cmToIn(Number(form.height_cm)) : form.height_cm);
}

function syncStoredFromDisplay() {
    form.target_weight_kg = weightDisplay.value === '' ? '' : form.weight_unit === 'lb' ? lbToKg(Number(weightDisplay.value)) : Number(weightDisplay.value);
    form.height_cm = heightDisplay.value === '' ? '' : form.height_unit === 'in' ? inToCm(Number(heightDisplay.value)) : Number(heightDisplay.value);
}

function nextStep() {
    syncStoredFromDisplay();

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
    form.post('/onboarding');
}

watch(() => [form.weight_unit, form.height_unit], syncDisplayFromStored);
syncDisplayFromStored();
</script>

<template>
    <Head title="Set up Buff" />

    <section class="space-y-5">
        <header>
            <p class="text-sm text-muted-foreground">Welcome</p>
            <h1 class="text-3xl font-semibold tracking-normal text-foreground">Set up Buff</h1>
        </header>

        <div class="grid grid-cols-3 gap-2">
            <div
                v-for="(label, index) in steps"
                :key="label"
                class="rounded-md border px-3 py-2 text-center text-sm font-semibold"
                :class="index === step ? 'border-primary bg-secondary text-foreground' : 'border-border text-muted-foreground'"
            >
                {{ label }}
            </div>
        </div>

        <Card v-if="step === 0">
            <div class="space-y-3">
                <h2 class="font-semibold">Choose units</h2>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Weight</span>
                    <Select v-model="form.weight_unit" class="mt-1">
                        <option value="kg">Kilograms</option>
                        <option value="lb">Pounds</option>
                    </Select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Height</span>
                    <Select v-model="form.height_unit" class="mt-1">
                        <option value="cm">Centimeters</option>
                        <option value="in">Inches</option>
                    </Select>
                </label>
            </div>
        </Card>

        <Card v-if="step === 1">
            <div class="space-y-3">
                <h2 class="font-semibold">Daily targets</h2>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Calories</span>
                    <Input v-model.number="form.calories" type="number" min="1" class="mt-1" />
                    <span v-if="form.errors.calories" class="mt-1 block text-sm text-destructive">{{ form.errors.calories }}</span>
                </label>
                <div class="grid grid-cols-3 gap-2">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Protein</span>
                        <Input v-model.number="form.protein_g" type="number" min="0" step="0.1" class="mt-1" />
                    </label>
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Carbs</span>
                        <Input v-model.number="form.carbs_g" type="number" min="0" step="0.1" class="mt-1" />
                    </label>
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Fat</span>
                        <Input v-model.number="form.fat_g" type="number" min="0" step="0.1" class="mt-1" />
                    </label>
                </div>
                <p class="rounded-md p-3 text-sm" :class="macrosMatch ? 'bg-success-soft text-success-soft-foreground' : 'bg-danger-soft text-danger-soft-foreground'">
                    {{ macroCalories }} kcal from macros
                </p>
            </div>
        </Card>

        <Card v-if="step === 2">
            <div class="space-y-3">
                <h2 class="font-semibold">Body profile</h2>
                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Height {{ form.height_unit }}</span>
                        <Input v-model="heightDisplay" type="number" min="1" step="0.1" class="mt-1" />
                        <span v-if="form.errors.height_cm" class="mt-1 block text-sm text-destructive">{{ form.errors.height_cm }}</span>
                    </label>
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Target {{ form.weight_unit }}</span>
                        <Input v-model="weightDisplay" type="number" min="1" step="0.1" class="mt-1" />
                        <span v-if="form.errors.target_weight_kg" class="mt-1 block text-sm text-destructive">{{ form.errors.target_weight_kg }}</span>
                    </label>
                </div>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Target body fat %</span>
                    <Input v-model.number="form.target_body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" />
                    <span v-if="form.errors.target_body_fat_percent" class="mt-1 block text-sm text-destructive">{{ form.errors.target_body_fat_percent }}</span>
                </label>
            </div>
        </Card>

        <div class="grid grid-cols-2 gap-2">
            <Button type="button" variant="surface" :disabled="step === 0" @click="previousStep">
                <ArrowLeft :size="18" />
                Back
            </Button>
            <Button v-if="step < steps.length - 1" type="button" :disabled="step === 1 && !macrosMatch" @click="nextStep">
                Next
                <ArrowRight :size="18" />
            </Button>
            <Button v-else type="button" :disabled="form.processing" @click="finish">
                <Check :size="18" />
                Start
            </Button>
        </div>
    </section>
</template>
