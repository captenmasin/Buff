<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Input from '../../Components/ui/input/Input.vue';
import Switch from '../../Components/ui/switch/Switch.vue';

type MealType = 'breakfast' | 'lunch' | 'dinner';

type MealReminders = Record<MealType, {
    enabled: boolean;
    time: string;
}>;

const props = defineProps<{
    mealReminders: MealReminders;
}>();

const mealReminderForm = useForm<MealReminders>({
    breakfast: {...props.mealReminders.breakfast},
    lunch: {...props.mealReminders.lunch},
    dinner: {...props.mealReminders.dinner},
});
const mealReminderOptions: Array<{ id: MealType; label: string }> = [
    {id: 'breakfast', label: 'Breakfast'},
    {id: 'lunch', label: 'Lunch'},
    {id: 'dinner', label: 'Dinner'},
];

function saveMealReminders() {
    if (mealReminderForm.processing) {
        return;
    }

    mealReminderForm.put('/settings/meal-reminders', {preserveScroll: true});
}

function mealReminderError(meal: MealType, field: 'enabled' | 'time') {
    return mealReminderForm.errors[`${meal}.${field}`];
}
</script>

<template>
    <Head title="Meal reminders"/>

    <section class="space-y-5">
        <SettingsPageHeader>Meal reminders</SettingsPageHeader>

        <Card>
            <div class="space-y-3">
                <div>
                    <h2 class="card-title">Meal reminders</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Get a reminder to log each meal.</p>
                </div>

                <div class="divide-y divide-border/60">
                    <div v-for="meal in mealReminderOptions" :key="meal.id" class="py-3 first:pt-1 last:pb-1">
                        <div class="flex items-center gap-3">
                            <label :for="`${meal.id}-reminder-enabled`" class="min-w-0 flex-1 font-medium">
                                {{ meal.label }}
                            </label>
                            <Input
                                :id="`${meal.id}-reminder-time`"
                                v-model="mealReminderForm[meal.id].time"
                                type="time"
                                :aria-label="`${meal.label} reminder time`"
                                class="w-[7.5rem] shrink-0 border-0 bg-transparent px-0 py-1 text-right text-sm tabular-nums shadow-none focus:border-transparent focus:bg-transparent focus-visible:ring-2 focus-visible:ring-ring"
                                @change="saveMealReminders"
                            />
                            <Switch
                                :id="`${meal.id}-reminder-enabled`"
                                v-model="mealReminderForm[meal.id].enabled"
                                :aria-label="`Enable ${meal.label.toLowerCase()} reminder`"
                                class="shrink-0"
                                @change="saveMealReminders"
                            />
                        </div>

                        <span v-if="mealReminderError(meal.id, 'enabled')" class="mt-1 block text-sm text-destructive">
                            {{ mealReminderError(meal.id, 'enabled') }}
                        </span>
                        <span v-if="mealReminderError(meal.id, 'time')" class="mt-1 block text-sm text-destructive">
                            {{ mealReminderError(meal.id, 'time') }}
                        </span>
                    </div>
                </div>
            </div>
        </Card>
    </section>
</template>
