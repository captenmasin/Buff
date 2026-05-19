<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    goal: { type: Object, required: true },
});

const form = useForm({
    starts_on: props.goal.starts_on,
    calories: props.goal.calories,
    protein_g: props.goal.protein_g,
    carbs_g: props.goal.carbs_g,
    fat_g: props.goal.fat_g,
});

const macroCalories = computed(() => {
    return Math.round((Number(form.protein_g) * 4) + (Number(form.carbs_g) * 4) + (Number(form.fat_g) * 9));
});

const matchesGoal = computed(() => macroCalories.value === Math.round(Number(form.calories)));

function percent(value, multiplier) {
    const calories = Number(value) * multiplier;

    if (!Number(form.calories)) return 0;

    return Math.round((calories / Number(form.calories)) * 100);
}

function save() {
    form.put('/goals', { preserveScroll: true });
}
</script>

<template>
    <Head title="Goals" />

    <section class="space-y-5">
        <header>
            <p class="text-sm font-semibold text-stone-500">Daily target</p>
            <h1 class="text-3xl font-bold tracking-normal text-[#17211b]">Goals</h1>
        </header>

        <form class="space-y-4" @submit.prevent="save">
            <article class="rounded-md border border-stone-200 bg-white p-4 shadow-sm">
                <label>
                    <span class="text-xs font-bold uppercase text-stone-500">Starts on</span>
                    <input
                        v-model="form.starts_on"
                        type="date"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-base font-semibold outline-none focus:border-[#6f9b58]"
                    >
                </label>
                <p v-if="form.errors.starts_on" class="mt-1 text-sm font-semibold text-red-700">{{ form.errors.starts_on }}</p>

                <label class="mt-4 block">
                    <span class="text-xs font-bold uppercase text-stone-500">Calories</span>
                    <input
                        v-model.number="form.calories"
                        type="number"
                        min="1"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-2xl font-bold outline-none focus:border-[#6f9b58]"
                    >
                </label>
                <p v-if="form.errors.calories" class="mt-1 text-sm font-semibold text-red-700">{{ form.errors.calories }}</p>
            </article>

            <article class="rounded-md border border-stone-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold">Macros</h2>
                        <p class="text-sm text-stone-500">{{ macroCalories }} kcal from macros</p>
                    </div>
                    <span
                        class="rounded-md px-3 py-2 text-xs font-bold"
                        :class="matchesGoal ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'"
                    >
                        {{ matchesGoal ? 'Matches' : 'Mismatch' }}
                    </span>
                </div>

                <div class="space-y-3">
                    <label v-for="macro in [
                        ['protein_g', 'Protein', 4],
                        ['carbs_g', 'Carbs', 4],
                        ['fat_g', 'Fat', 9],
                    ]" :key="macro[0]" class="grid grid-cols-[1fr_8rem] items-center gap-3">
                        <span>
                            <span class="block text-sm font-bold">{{ macro[1] }}</span>
                            <span class="text-xs font-semibold text-stone-500">{{ percent(form[macro[0]], macro[2]) }}%</span>
                        </span>
                        <input
                            v-model.number="form[macro[0]]"
                            type="number"
                            min="0"
                            step="0.1"
                            class="w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 text-right font-bold outline-none focus:border-[#6f9b58]"
                        >
                        <p v-if="form.errors[macro[0]]" class="col-span-2 text-sm font-semibold text-red-700">{{ form.errors[macro[0]] }}</p>
                    </label>
                </div>
            </article>

            <button class="w-full rounded-md bg-[#253d2c] px-4 py-4 text-base font-bold text-white active:bg-[#17211b]" :disabled="form.processing">
                Save goals
            </button>
        </form>
    </section>
</template>
