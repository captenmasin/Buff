<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import {watch} from 'vue';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Select from '../../Components/ui/select/Select.vue';
import SelectContent from '../../Components/ui/select/SelectContent.vue';
import SelectItem from '../../Components/ui/select/SelectItem.vue';
import SelectTrigger from '../../Components/ui/select/SelectTrigger.vue';
import SelectValue from '../../Components/ui/select/SelectValue.vue';
import {type HeightUnit, type MeasurementUnit, type WeightUnit} from '../../bodyUnits';
import {useQueuedSave} from '../../useQueuedSave';

const props = defineProps<{
    preferences: {
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
        measurement_unit: MeasurementUnit;
    };
}>();

const unitForm = useForm({
    weight_unit: props.preferences.weight_unit,
    height_unit: props.preferences.height_unit,
    measurement_unit: props.preferences.measurement_unit,
});

const {save: saveUnits, status: saveStatus} = useQueuedSave((callbacks) => {
    unitForm.put('/settings/units', {preserveScroll: true, ...callbacks});
});

watch(
    () => [unitForm.weight_unit, unitForm.height_unit, unitForm.measurement_unit] as const,
    () => {
        saveUnits();
    },
);
</script>

<template>
    <Head title="Units"/>

    <section class="space-y-5">
        <SettingsPageHeader>Units</SettingsPageHeader>

        <Card>
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="card-title">Units</h2>
                    <p class="text-sm text-muted-foreground" role="status" aria-live="polite">
                        {{ saveStatus === 'saving' ? 'Saving…' : saveStatus === 'saved' ? 'Saved' : saveStatus === 'error' ? 'Couldn’t save' : '' }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label>
                        <span class="field-label">Weight</span>
                        <Select v-model="unitForm.weight_unit" class="mt-1">
                            <SelectTrigger>
                                <SelectValue placeholder="Select weight unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="kg">Kilograms</SelectItem>
                                <SelectItem value="lb">Pounds</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="unitForm.errors.weight_unit" class="mt-1 block text-sm text-destructive">{{ unitForm.errors.weight_unit }}</span>
                    </label>

                    <label>
                        <span class="field-label">Height</span>
                        <Select v-model="unitForm.height_unit" class="mt-1">
                            <SelectTrigger>
                                <SelectValue placeholder="Select height unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cm">Centimeters</SelectItem>
                                <SelectItem value="in">Feet and inches</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="unitForm.errors.height_unit" class="mt-1 block text-sm text-destructive">{{ unitForm.errors.height_unit }}</span>
                    </label>

                    <label>
                        <span class="field-label">Measurements</span>
                        <Select v-model="unitForm.measurement_unit" class="mt-1">
                            <SelectTrigger>
                                <SelectValue placeholder="Select measurement unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cm">Centimeters</SelectItem>
                                <SelectItem value="in">Inches</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="unitForm.errors.measurement_unit" class="mt-1 block text-sm text-destructive">{{ unitForm.errors.measurement_unit }}</span>
                    </label>
                </div>
            </div>
        </Card>
    </section>
</template>
