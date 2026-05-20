<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { TrendingDown, TrendingUp } from '@lucide/vue';
import Card from "../Components/Card.vue";

const props = defineProps({
    today: { type: String, required: true },
    latest: { type: Object, default: null },
    delta: { type: Object, default: null },
    history: { type: Array, required: true },
});

const form = useForm({
    date: props.today,
    weight_kg: props.latest?.date === props.today ? props.latest.weight_kg : '',
    body_fat_percent: props.latest?.date === props.today ? props.latest.body_fat_percent : '',
    notes: props.latest?.date === props.today ? props.latest.notes : '',
});

const hasDelta = computed(() => Boolean(props.delta));

function deltaLabel(value, suffix) {
    if (value === null || value === undefined) return 'No change';

    const sign = value > 0 ? '+' : '';

    return `${sign}${value}${suffix}`;
}

function save() {
    form.post('/progress/body-metrics', { preserveScroll: true });
}
</script>

<template>
    <Head title="Progress" />

    <section class="space-y-5">
        <header>
            <p class="text-sm font-semibold text-stone-500">Body metrics</p>
            <h1 class="text-3xl font-bold tracking-normal text-[#17211b]">Progress</h1>
        </header>

        <article class="grid grid-cols-2 gap-3">
            <Card>
                <p class="text-xs font-bold uppercase text-stone-500">Weight</p>
                <p class="mt-2 text-3xl font-bold">{{ latest?.weight_kg ?? '--' }}<span class="text-sm text-stone-500"> kg</span></p>
                <p class="mt-1 flex items-center gap-1 text-sm font-semibold" :class="delta?.weight_kg > 0 ? 'text-red-700' : 'text-emerald-700'">
                    <component :is="delta?.weight_kg > 0 ? TrendingUp : TrendingDown" v-if="hasDelta" :size="15" />
                    {{ hasDelta ? deltaLabel(delta.weight_kg, ' kg') : 'First entry' }}
                </p>
            </Card>

            <Card>
                <p class="text-xs font-bold uppercase text-stone-500">Body fat</p>
                <p class="mt-2 text-3xl font-bold">{{ latest?.body_fat_percent ?? '--' }}<span class="text-sm text-stone-500">%</span></p>
                <p class="mt-1 flex items-center gap-1 text-sm font-semibold" :class="delta?.body_fat_percent > 0 ? 'text-red-700' : 'text-emerald-700'">
                    <component :is="delta?.body_fat_percent > 0 ? TrendingUp : TrendingDown" v-if="hasDelta && delta.body_fat_percent !== null" :size="15" />
                    {{ hasDelta ? deltaLabel(delta.body_fat_percent, '%') : 'First entry' }}
                </p>
            </Card>
        </article>

        <Card>
        <form class="space-y-3" @submit.prevent="save">
            <h2 class="font-bold">Log current</h2>

            <label class="block">
                <span class="text-xs font-bold uppercase text-stone-500">Date</span>
                <input
                    v-model="form.date"
                    type="date"
                    class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                >
                <span v-if="form.errors.date" class="mt-1 block text-sm font-semibold text-red-700">{{ form.errors.date }}</span>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label>
                    <span class="text-xs font-bold uppercase text-stone-500">Weight kg</span>
                    <input
                        v-model="form.weight_kg"
                        type="number"
                        min="1"
                        max="1000"
                        step="0.1"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="form.errors.weight_kg" class="mt-1 block text-sm font-semibold text-red-700">{{ form.errors.weight_kg }}</span>
                </label>

                <label>
                    <span class="text-xs font-bold uppercase text-stone-500">Body fat %</span>
                    <input
                        v-model="form.body_fat_percent"
                        type="number"
                        min="1"
                        max="80"
                        step="0.1"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="form.errors.body_fat_percent" class="mt-1 block text-sm font-semibold text-red-700">{{ form.errors.body_fat_percent }}</span>
                </label>
            </div>

            <label class="block">
                <span class="text-xs font-bold uppercase text-stone-500">Notes</span>
                <textarea
                    v-model="form.notes"
                    rows="3"
                    class="mt-1 w-full resize-none rounded-md border border-stone-200 bg-stone-50 px-3 py-3 font-semibold outline-none focus:border-[#6f9b58]"
                />
                <span v-if="form.errors.notes" class="mt-1 block text-sm font-semibold text-red-700">{{ form.errors.notes }}</span>
            </label>

            <button class="w-full rounded-md bg-[#253d2c] px-4 py-3 font-bold text-white active:bg-[#17211b]" :disabled="form.processing">
                Save progress
            </button>
        </form>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-bold">Recent history</h2>

            <Card v-if="history.length" class="divide-y divide-stone-100">
                <div v-for="metric in history" :key="metric.id" class="py-3 first:pt-0 last:pb-0">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-bold">{{ metric.date }}</p>
                        <p class="text-sm font-semibold text-stone-500">
                            {{ metric.weight_kg }} kg
                            <span v-if="metric.body_fat_percent !== null"> · {{ metric.body_fat_percent }}%</span>
                        </p>
                    </div>
                    <p v-if="metric.notes" class="mt-1 text-sm text-stone-500">{{ metric.notes }}</p>
                </div>
            </Card>

            <Card v-else class="text-sm font-semibold text-stone-500">No progress entries yet.</Card>
        </section>
    </section>
</template>
