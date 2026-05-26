<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Download, Moon, Smartphone, Sun, Upload } from '@lucide/vue';
import { applyAppearance, saveAppearance, storedAppearance, type Appearance } from '../appearance';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';
import { heightFromCm, heightToCm, weightFromKg, weightToKg, type HeightUnit, type WeightUnit } from '../bodyUnits';

const props = defineProps<{
    settings: {
        height_cm: number | null;
        target_weight_kg: number | null;
        target_body_fat_percent: number | null;
    };
    preferences: {
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
    };
}>();

const bodyTargetForm = useForm({
    target_weight_kg: weightFromKg(props.settings.target_weight_kg, props.preferences.weight_unit) ?? '',
    target_body_fat_percent: props.settings.target_body_fat_percent ?? '',
});

const heightForm = useForm({
    height_cm: heightFromCm(props.settings.height_cm, props.preferences.height_unit) ?? '',
});

const unitForm = useForm({
    weight_unit: props.preferences.weight_unit,
    height_unit: props.preferences.height_unit,
});

const importForm = useForm<{
    export: File | null;
}>({
    export: null,
});

const appearance = ref<Appearance>(storedAppearance());
const importInput = ref<HTMLInputElement | null>(null);

const appearanceOptions: Array<{ value: Appearance; label: string; icon: typeof Sun }> = [
    { value: 'system', label: 'System', icon: Smartphone },
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
];

function saveBodyTargets() {
    bodyTargetForm
        .transform((data) => ({
            ...data,
            target_weight_kg: weightToKg(data.target_weight_kg, unitForm.weight_unit),
        }))
        .put('/settings/body-targets', { preserveScroll: true });
}

function saveHeight() {
    heightForm
        .transform((data) => ({
            ...data,
            height_cm: heightToCm(data.height_cm, unitForm.height_unit),
        }))
        .put('/settings/height', { preserveScroll: true });
}

function saveUnits() {
    unitForm.put('/settings/units', { preserveScroll: true });
}

function chooseImportFile() {
    importInput.value?.click();
}

function importData(event: Event) {
    const target = event.target instanceof HTMLInputElement ? event.target : null;
    const file = target?.files?.[0] ?? null;

    if (!file) {
        return;
    }

    importForm.export = file;
    importForm.post('/settings/import', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            importForm.reset();

            if (target) {
                target.value = '';
            }
        },
    });
}

function selectAppearance(value: Appearance) {
    appearance.value = value;
    saveAppearance(value);
    applyAppearance(value);
}

watch(
    () => [unitForm.weight_unit, unitForm.height_unit] as const,
    ([,], [previousWeightUnit, previousHeightUnit]) => {
        const currentTargetWeightKg = weightToKg(bodyTargetForm.target_weight_kg, previousWeightUnit);
        const currentHeightCm = heightToCm(heightForm.height_cm, previousHeightUnit);

        bodyTargetForm.target_weight_kg = currentTargetWeightKg === '' ? '' : weightFromKg(Number(currentTargetWeightKg), unitForm.weight_unit) ?? '';
        heightForm.height_cm = currentHeightCm === '' ? '' : heightFromCm(Number(currentHeightCm), unitForm.height_unit) ?? '';
    },
);
</script>

<template>
    <Head title="Settings" />

    <section class="space-y-5">
        <header>
            <p class="text-sm text-muted-foreground">Preferences</p>
            <h1 class="text-3xl font-semibold tracking-normal text-foreground">Settings</h1>
        </header>

        <Card>
            <h2 class="font-semibold">Appearance</h2>
            <div class="mt-3 grid grid-cols-3 gap-2">
                <Button
                    v-for="option in appearanceOptions"
                    :key="option.value"
                    type="button"
                    class="flex-col px-2 text-sm"
                    :variant="appearance === option.value ? 'default' : 'surface'"
                    @click="selectAppearance(option.value)"
                >
                    {{ option.label }}
                </Button>
            </div>
        </Card>

        <Card>
            <form class="space-y-3" @submit.prevent="saveUnits">
                <h2 class="font-semibold">Units</h2>

                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Weight</span>
                        <Select v-model="unitForm.weight_unit" class="mt-1">
                            <option value="kg">Kilograms</option>
                            <option value="lb">Pounds</option>
                        </Select>
                        <span v-if="unitForm.errors.weight_unit" class="mt-1 block text-sm text-destructive">{{ unitForm.errors.weight_unit }}</span>
                    </label>

                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Height</span>
                        <Select v-model="unitForm.height_unit" class="mt-1">
                            <option value="cm">Centimeters</option>
                            <option value="in">Inches</option>
                        </Select>
                        <span v-if="unitForm.errors.height_unit" class="mt-1 block text-sm text-destructive">{{ unitForm.errors.height_unit }}</span>
                    </label>
                </div>

                <Button class="w-full" :disabled="unitForm.processing">
                    Save units
                </Button>
            </form>
        </Card>

        <Card>
            <form class="space-y-3" @submit.prevent="saveBodyTargets">
                <h2 class="font-semibold">Body targets</h2>

                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Weight {{ unitForm.weight_unit }}</span>
                        <Input
                            v-model.number="bodyTargetForm.target_weight_kg"
                            type="number"
                            min="1"
                            :max="unitForm.weight_unit === 'lb' ? 2200 : 1000"
                            step="0.1"
                            class="mt-1"
                        />
                        <span v-if="bodyTargetForm.errors.target_weight_kg" class="mt-1 block text-sm text-destructive">{{ bodyTargetForm.errors.target_weight_kg }}</span>
                    </label>

                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Body fat %</span>
                        <Input
                            v-model.number="bodyTargetForm.target_body_fat_percent"
                            type="number"
                            min="1"
                            max="80"
                            step="0.1"
                            class="mt-1"
                        />
                        <span v-if="bodyTargetForm.errors.target_body_fat_percent" class="mt-1 block text-sm text-destructive">{{ bodyTargetForm.errors.target_body_fat_percent }}</span>
                    </label>
                </div>

                <Button class="w-full" :disabled="bodyTargetForm.processing">
                    Save targets
                </Button>
            </form>
        </Card>

        <Card>
            <form class="space-y-3" @submit.prevent="saveHeight">
                <h2 class="font-semibold">Height</h2>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Height {{ unitForm.height_unit }}</span>
                    <Input
                        v-model.number="heightForm.height_cm"
                        type="number"
                        :min="unitForm.height_unit === 'in' ? 20 : 50"
                        :max="unitForm.height_unit === 'in' ? 102 : 260"
                        step="0.1"
                        class="mt-1"
                    />
                    <span v-if="heightForm.errors.height_cm" class="mt-1 block text-sm text-destructive">{{ heightForm.errors.height_cm }}</span>
                </label>
                <Button class="w-full" :disabled="heightForm.processing">
                    Save height
                </Button>
            </form>
        </Card>

        <Card>
            <h2 class="font-semibold">Import / export</h2>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <Button as="a" href="/settings/export" variant="surface" class="h-auto flex-col px-3 py-4 text-sm">
                    <Download :size="20" />
                    Export
                </Button>
                <Button type="button" variant="surface" class="h-auto flex-col px-3 py-4 text-sm" :disabled="importForm.processing" @click="chooseImportFile">
                    <Upload :size="20" />
                    Import
                </Button>
            </div>
            <input ref="importInput" type="file" accept="application/json,.json" class="hidden" @change="importData">
            <p v-if="importForm.errors.export" class="mt-2 text-sm text-destructive">{{ importForm.errors.export }}</p>
        </Card>
    </section>
</template>
