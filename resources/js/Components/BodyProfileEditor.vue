<script setup lang="ts">
import Input from './ui/input/Input.vue';
import Select from './ui/select/Select.vue';
import SelectContent from './ui/select/SelectContent.vue';
import SelectItem from './ui/select/SelectItem.vue';
import SelectTrigger from './ui/select/SelectTrigger.vue';
import SelectValue from './ui/select/SelectValue.vue';
import { ref, watch } from 'vue';
import { activityLevelOptions, type ActivityLevel, type Sex, sexOptions } from '../bodyProfile';
import { feetAndInchesFromInches, inchesFromFeetAndInches, type HeightUnit } from '../bodyUnits';

const age = defineModel<string | number>('age', { required: true });
const sex = defineModel<string | Sex>('sex', { required: true });
const height = defineModel<string | number>('height', { required: true });
const activityLevel = defineModel<string | ActivityLevel>('activity_level', { required: true });
const initialHeight = feetAndInchesFromInches(height.value);
const heightFeet = ref<string | number>(initialHeight.feet);
const heightInches = ref<string | number>(initialHeight.inches);
let syncingHeight = false;

watch(height, (value) => {
    if (syncingHeight) {
        return;
    }

    syncingHeight = true;
    const parts = feetAndInchesFromInches(value);
    heightFeet.value = parts.feet;
    heightInches.value = parts.inches;
    syncingHeight = false;
}, { flush: 'sync' });

watch([heightFeet, heightInches], ([feet, inches]) => {
    if (syncingHeight) {
        return;
    }

    syncingHeight = true;
    height.value = inchesFromFeetAndInches(feet, inches);
    syncingHeight = false;
}, { flush: 'sync' });

defineProps<{
    heightUnit: HeightUnit;
    errors?: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <label>
                <span class="field-label">Age</span>
                <Input v-model="age" type="number" min="13" max="120" class="mt-1" />
                <span v-if="errors?.age" class="mt-1 block text-sm text-destructive">{{ errors.age }}</span>
            </label>
            <label>
                <span class="field-label">Sex</span>
                <Select v-model="sex" class="mt-1">
                    <SelectTrigger>
                        <SelectValue placeholder="Optional" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in sexOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <span v-if="errors?.sex" class="mt-1 block text-sm text-destructive">{{ errors.sex }}</span>
            </label>
        </div>
        <div v-if="heightUnit === 'in'">
            <div class="grid grid-cols-2 gap-3">
                <label>
                    <span class="field-label">Feet</span>
                    <Input v-model.number="heightFeet" type="number" min="1" step="1" class="mt-1" :aria-invalid="Boolean(errors?.height_cm)" />
                </label>
                <label>
                    <span class="field-label">Inches</span>
                    <Input v-model.number="heightInches" type="number" min="0" max="11.9" step="0.1" class="mt-1" :aria-invalid="Boolean(errors?.height_cm)" />
                </label>
            </div>
            <span v-if="errors?.height_cm" class="mt-1 block text-sm text-destructive">{{ errors.height_cm }}</span>
        </div>
        <label v-else class="block">
            <span class="field-label">Height cm</span>
            <Input v-model="height" type="number" min="1" step="0.1" class="mt-1" :aria-invalid="Boolean(errors?.height_cm)" />
            <span v-if="errors?.height_cm" class="mt-1 block text-sm text-destructive">{{ errors.height_cm }}</span>
        </label>
        <label class="block">
            <span class="field-label">Activity</span>
            <Select v-model="activityLevel" class="mt-1">
                <SelectTrigger>
                    <SelectValue placeholder="Typical week" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in activityLevelOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                        <template #description>
                            {{ option.description }}
                        </template>
                    </SelectItem>
                </SelectContent>
            </Select>
            <span class="mt-1 block text-xs text-muted-foreground">How much you move in a typical week.</span>
            <span v-if="errors?.activity_level" class="mt-1 block text-sm text-destructive">{{ errors.activity_level }}</span>
        </label>
    </div>
</template>
