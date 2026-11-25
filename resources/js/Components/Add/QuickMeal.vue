<script setup lang="ts">
import {Link, useForm} from '@inertiajs/vue3';
import {computed} from 'vue';
import {hapticImpact} from '../../haptics';
import {mealLabels, mealSuggestionUrl, type MealSuggestion} from '../../mealSuggestions';
import Card from '../Card.vue';
import Button from '../ui/button/Button.vue';

const props = defineProps<{date: string; meal: MealSuggestion}>();
const form = useForm({
    date: props.date,
    meal_type: props.meal.meal_type,
    quick_log: true,
});
const portion = computed(() => {
    const {portion_quantity: quantity, portion_unit: unit} = props.meal;

    if (quantity === null) return '';
    if (unit !== null) return `${quantity}${unit}`;

    return `${quantity} serving${quantity === 1 ? '' : 's'}`;
});

function logMeal(): void {
    hapticImpact();

    if (props.meal.kind === 'recipe') {
        form.transform((data) => ({...data, recipe_id: props.meal.id, servings: props.meal.portion_quantity}))
            .post('/meals/recipe', {preserveScroll: true});

        return;
    }

    form.post(`/meals/${props.meal.id}/repeat`, {preserveScroll: true});
}
</script>

<template>
    <Card class="gap-3">
        <div>
            <h2 class="card-title break-words">{{ meal.name }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ mealLabels[meal.meal_type] }}<template v-if="portion"> · {{ portion }}</template><template v-if="meal.protein_g > 0"> · {{ meal.protein_g }} g protein</template>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button type="button" variant="surface" :loading="form.processing" loading-label="Logging meal…" @click="logMeal">
                {{ meal.kind === 'entry' ? 'Log again' : 'Log recipe' }}
            </Button>
            <Button v-if="portion && !form.processing" :as="Link" :href="mealSuggestionUrl(date, meal)" variant="outline">
                Adjust portion
            </Button>
        </div>
        <p v-if="form.hasErrors" class="text-sm text-destructive" role="alert">{{ Object.values(form.errors)[0] }}</p>
    </Card>
</template>
