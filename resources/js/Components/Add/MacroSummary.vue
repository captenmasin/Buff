<script setup lang="ts">
defineProps<{
    macros: {
        calories: number;
        protein_g: number;
        carbs_g: number;
        fat_g: number;
    };
}>();

function formatMacro(value: number) {
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}
</script>

<template>
    <div class="grid grid-cols-4 gap-2 rounded-xl bg-muted/80 p-3 text-center">
        <div>
            <p class="field-label">Kcal</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ macros.calories }}</p>
        </div>
        <div>
            <p class="field-label">Protein</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(macros.protein_g) }}g</p>
        </div>
        <div>
            <p class="field-label">Carbs</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(macros.carbs_g) }}g</p>
        </div>
        <div>
            <p class="field-label">Fat</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(macros.fat_g) }}g</p>
        </div>
    </div>
</template>
