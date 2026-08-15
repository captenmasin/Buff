<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowLeft, ArrowRight, Check } from '@lucide/vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';
import { heightFromCm, heightToCm, weightFromKg, weightToKg, type HeightUnit, type WeightUnit } from '../bodyUnits';

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

const page = usePage<{
    buff: {
        needs_sign_in: boolean;
        has_local_account: boolean;
    };
}>();
const step = ref(0);
const weightDisplay = ref('');
const heightDisplay = ref('');
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

const registrationForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    timezone,
});

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

const requiresRegistration = computed(() => page.props.buff.needs_sign_in
    && !page.props.buff.has_local_account);
const steps = computed(() => requiresRegistration.value
    ? ['Account', 'Units', 'Goals', 'Body']
    : ['Units', 'Goals', 'Body']);
const currentStep = computed(() => steps.value[step.value]);
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
    if (currentStep.value === 'Account') {
        registrationForm.post('/account/register');

        return;
    }

    syncStoredFromDisplay();

    if (step.value < steps.value.length - 1) {
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

    <form class="space-y-5" @submit.prevent="currentStep === 'Body' ? finish() : nextStep()">
        <header>
            <p class="text-sm text-muted-foreground">Welcome</p>
            <h1 class="text-3xl font-semibold tracking-normal text-foreground">Set up Buff</h1>
        </header>

        <div class="grid gap-2" :class="steps.length === 4 ? 'grid-cols-4' : 'grid-cols-3'">
            <div
                v-for="(label, index) in steps"
                :key="label"
                class="rounded-md border px-3 py-2 text-center text-sm font-semibold"
                :class="index === step ? 'border-primary bg-secondary text-foreground' : 'border-border text-muted-foreground'"
            >
                {{ label }}
            </div>
        </div>

        <Card v-if="currentStep === 'Account'">
            <div class="space-y-3">
                <h2 class="font-semibold">Create your account</h2>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Name</span>
                    <Input v-model="registrationForm.name" autocomplete="name" required class="mt-1" />
                    <span v-if="registrationForm.errors.name" class="mt-1 block text-sm text-destructive">{{ registrationForm.errors.name }}</span>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Email</span>
                    <Input v-model="registrationForm.email" type="email" autocomplete="email" required class="mt-1" />
                    <span v-if="registrationForm.errors.email" class="mt-1 block text-sm text-destructive">{{ registrationForm.errors.email }}</span>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Password</span>
                    <Input v-model="registrationForm.password" type="password" autocomplete="new-password" minlength="8" required class="mt-1" />
                    <span v-if="registrationForm.errors.password" class="mt-1 block text-sm text-destructive">{{ registrationForm.errors.password }}</span>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Confirm password</span>
                    <Input v-model="registrationForm.password_confirmation" type="password" autocomplete="new-password" minlength="8" required class="mt-1" />
                </label>
                <p class="text-center text-sm">Already registered? <Link href="/account/login" class="text-primary">Sign in</Link></p>
            </div>
        </Card>

        <Card v-if="currentStep === 'Units'">
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

        <Card v-if="currentStep === 'Goals'">
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

        <Card v-if="currentStep === 'Body'">
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
            <Button
                v-if="step < steps.length - 1"
                type="submit"
                :disabled="(currentStep === 'Goals' && !macrosMatch) || registrationForm.processing"
            >
                {{ registrationForm.processing ? 'Creating...' : 'Next' }}
                <ArrowRight :size="18" />
            </Button>
            <Button v-else type="submit" :disabled="form.processing">
                <Check :size="18" />
                Start
            </Button>
        </div>
    </form>
</template>
