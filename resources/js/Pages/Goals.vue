<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Card from '../Components/Card.vue';
import DailyTargetsEditor from '../Components/DailyTargetsEditor.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { weightFromKg, weightToKg, type WeightUnit } from '../bodyUnits';

const props = defineProps<{
    preferences: { weight_unit: WeightUnit };
    goal: {
        calories: number;
        protein_g: number;
        carbs_g: number;
        fat_g: number;
        target_weight_kg: number | null;
        target_body_fat_percent: number | null;
    };
}>();

const targetsValid = ref(true);
const form = useForm({
    calories: props.goal.calories,
    protein_g: props.goal.protein_g,
    carbs_g: props.goal.carbs_g,
    fat_g: props.goal.fat_g,
    target_weight_kg: weightFromKg(props.goal.target_weight_kg, props.preferences.weight_unit) ?? '',
    target_body_fat_percent: props.goal.target_body_fat_percent ?? '',
});

function save(): void {
    form.transform((data) => ({
        ...data,
        target_weight_kg: weightToKg(data.target_weight_kg, props.preferences.weight_unit),
    })).put('/goals', { preserveScroll: true });
}
</script>

<template>
    <Head title="Goals" />
    <section class="space-y-5">
        <PageHeader>Goals</PageHeader>
        <form class="space-y-4" @submit.prevent="save">
            <DailyTargetsEditor
                v-model:calories="form.calories"
                v-model:protein_g="form.protein_g"
                v-model:carbs_g="form.carbs_g"
                v-model:fat_g="form.fat_g"
                :errors="form.errors"
                @valid="targetsValid = $event"
            />
            <Card class="space-y-3">
                <div>
                    <h2 class="card-title">Body targets</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Shown on Progress trends when set.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="field-label">Target {{ preferences.weight_unit }}</span>
                        <Input v-model="form.target_weight_kg" type="number" min="1" step="0.1" class="mt-1" />
                        <span v-if="form.errors.target_weight_kg" class="text-sm text-destructive">{{ form.errors.target_weight_kg }}</span>
                    </label>
                    <label>
                        <span class="field-label">Target body fat %</span>
                        <Input v-model="form.target_body_fat_percent" type="number" min="1" max="80" step="0.1" class="mt-1" />
                        <span v-if="form.errors.target_body_fat_percent" class="text-sm text-destructive">{{ form.errors.target_body_fat_percent }}</span>
                    </label>
                </div>
            </Card>
            <Button class="w-full" size="lg" :disabled="form.processing || !targetsValid">Save goals</Button>
        </form>
    </section>
</template>
