<script setup lang="ts">
import { getLocalTimeZone, today } from '@internationalized/date';
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useId } from 'vue';
import { hapticImpact } from '../../haptics';
import Button from '../ui/button/Button.vue';
import Input from '../ui/input/Input.vue';

const props = defineProps<{
    date?: string;
    replaceHistory?: boolean;
}>();

const emit = defineEmits<{
    saved: [];
}>();

const fieldId = useId();
const form = useForm({
    date: '',
    title: '',
    calories_burned: '' as number | '',
    time: new Date().toTimeString().slice(0, 5),
});

function addWorkout() {
    if (form.processing) {
        return;
    }

    hapticImpact();
    form.date = props.date ?? today(getLocalTimeZone()).toString();
    form.post('/workouts', {
        replace: props.replaceHistory ?? false,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <form class="grid gap-3" @submit.prevent="addWorkout">
        <label class="block">
            <span class="field-label">Title</span>
            <Input
                v-model="form.title"
                type="text"
                maxlength="120"
                class="mt-1"
                :aria-invalid="Boolean(form.errors.title)"
                :aria-describedby="form.errors.title ? `${fieldId}-title-error` : undefined"
                @update:model-value="form.clearErrors('title')"
            />
            <span v-if="form.errors.title" :id="`${fieldId}-title-error`" class="mt-1 block text-sm text-destructive" role="alert">{{ form.errors.title }}</span>
        </label>

        <div class="grid grid-cols-2 gap-2">
            <label class="min-w-0">
                <span class="field-label">Calories burnt</span>
                <Input
                    v-model.number="form.calories_burned"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="10000"
                    step="1"
                    placeholder="0"
                    class="mt-1 text-right font-semibold"
                    :aria-invalid="Boolean(form.errors.calories_burned)"
                    :aria-describedby="form.errors.calories_burned ? `${fieldId}-calories-error` : undefined"
                    @update:model-value="form.clearErrors('calories_burned')"
                />
                <span v-if="form.errors.calories_burned" :id="`${fieldId}-calories-error`" class="mt-1 block text-sm text-destructive" role="alert">{{ form.errors.calories_burned }}</span>
            </label>

            <label class="min-w-0">
                <span class="field-label">Time</span>
                <Input
                    v-model="form.time"
                    type="time"
                    class="mt-1 font-semibold"
                    :aria-invalid="Boolean(form.errors.time)"
                    :aria-describedby="form.errors.time ? `${fieldId}-time-error` : undefined"
                    @update:model-value="form.clearErrors('time')"
                />
                <span v-if="form.errors.time" :id="`${fieldId}-time-error`" class="mt-1 block text-sm text-destructive" role="alert">{{ form.errors.time }}</span>
            </label>
        </div>

        <p v-if="form.errors.date" class="text-sm text-destructive" role="alert">{{ form.errors.date }}</p>

        <Button type="submit" class="w-full" :loading="form.processing" loading-label="Adding workout…">
            <Plus :size="18" aria-hidden="true" />
            Add workout
        </Button>
    </form>
</template>
