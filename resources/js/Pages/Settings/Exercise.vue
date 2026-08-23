<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';
import {type HeightUnit, type WeightUnit} from '../../bodyUnits';

type EatBack = 'all' | 'half' | 'none';

const props = defineProps<{
    preferences: {
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
        eat_back: EatBack;
    };
}>();

const eatBackForm = useForm({
    eat_back: props.preferences.eat_back,
});
const eatBackOptions: Array<{ value: EatBack; label: string; description: string }> = [
    {value: 'all', label: 'Eat all back', description: 'Add every workout calorie to today’s food target and macros.'},
    {value: 'half', label: 'Eat half back', description: 'Add half. Useful when a watch or band tends to overestimate burn.'},
    {value: 'none', label: "Don't eat back", description: 'Keep the food target as set. Workouts still log; they just don’t unlock extra food.'},
];

function saveEatBack(eatBack: EatBack) {
    if (eatBackForm.processing) {
        return;
    }

    eatBackForm.eat_back = eatBack;
    eatBackForm.put('/settings/eat-back', {preserveScroll: true});
}
</script>

<template>
    <Head title="Exercise calories"/>

    <section class="space-y-5">
        <SettingsPageHeader>Exercise calories</SettingsPageHeader>

        <Card>
            <div class="space-y-3">
                <div>
                    <h2 class="card-title">Exercise calories</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        How much of a workout to add to today’s food target. Wearables often overestimate burn, and eating every unlocked calorie can stall a cut. The ring still shows everything you burned — this only changes remaining calories and macros.
                    </p>
                </div>
                <div class="grid gap-2">
                    <Button
                        v-for="option in eatBackOptions"
                        :key="option.value"
                        type="button"
                        class="h-auto w-full min-w-0 justify-start whitespace-normal rounded-2xl px-4 py-3 text-left"
                        :variant="eatBackForm.eat_back === option.value ? 'default' : 'surface'"
                        :disabled="eatBackForm.processing"
                        @click="saveEatBack(option.value)"
                    >
                        <span class="min-w-0">
                            <span class="block font-semibold">{{ option.label }}</span>
                            <span
                                class="mt-0.5 block text-sm font-medium"
                                :class="eatBackForm.eat_back === option.value ? 'text-primary-foreground/70' : 'text-muted-foreground'"
                            >
                                {{ option.description }}
                            </span>
                        </span>
                    </Button>
                </div>
                <p v-if="eatBackForm.errors.eat_back" class="text-sm text-destructive">{{ eatBackForm.errors.eat_back }}</p>
            </div>
        </Card>
    </section>
</template>
