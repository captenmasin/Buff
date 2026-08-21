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
    <div class="rounded-xl border border-border bg-muted p-3">
        <p class="text-sm font-semibold">When did you have it?</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <Button
                v-for="mealType in mealTypes"
                :key="mealType"
                type="button"
                class="min-h-11 px-3 text-sm"
                :variant="modelValue === mealType ? 'default' : 'inverse'"
                @click="emit('update:modelValue', mealType)"
            >
                {{ mealLabels[mealType] }}
            </Button>
        </div>
    </div>
</template>
