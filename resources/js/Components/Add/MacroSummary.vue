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
    <div class="grid grid-cols-4 gap-2 rounded-md border border-border bg-muted p-3 text-center">
        <div>
            <p class="text-xs font-semibold uppercase text-muted-foreground">Kcal</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ macros.calories }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-muted-foreground">Protein</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(macros.protein_g) }}g</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-muted-foreground">Carbs</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(macros.carbs_g) }}g</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase text-muted-foreground">Fat</p>
            <p class="mt-1 text-lg font-semibold text-foreground">{{ formatMacro(macros.fat_g) }}g</p>
        </div>
    </div>
</template>
