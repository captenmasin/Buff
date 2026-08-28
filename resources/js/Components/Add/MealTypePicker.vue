<script setup lang="ts">
import Button from '../ui/button/Button.vue';

type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snacks';

const mealLabels: Record<MealType, string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    dinner: 'Dinner',
    snacks: 'Snacks',
};

defineProps<{
    mealTypes: MealType[];
    modelValue: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: MealType];
}>();
</script>

<template>
    <div>
        <p class="text-sm font-semibold">When did you have it?</p>
        <div class="mt-3 grid grid-cols-2 gap-2" role="radiogroup" aria-label="Meal type">
            <Button
                v-for="mealType in mealTypes"
                :key="mealType"
                type="button"
                role="radio"
                variant="surface"
                class="min-h-11 px-3 text-sm"
                :class="modelValue === mealType ? 'border-brand-violet bg-brand-violet/10 ring-1 ring-brand-violet' : ''"
                :aria-checked="modelValue === mealType"
                @click="emit('update:modelValue', mealType)"
            >
                {{ mealLabels[mealType] }}
            </Button>
        </div>
    </div>
</template>
