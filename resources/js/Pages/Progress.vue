<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Trash2, TrendingDown, TrendingUp } from '@lucide/vue';
import Card from "../Components/Card.vue";

const props = defineProps({
    today: { type: String, required: true },
    latest: { type: Object, default: null },
    goals: { type: Object, default: null },
    delta: { type: Object, default: null },
    history: { type: Array, required: true },
});

const form = useForm({
    date: props.today,
    weight_kg: props.latest?.date === props.today ? props.latest.weight_kg : '',
    body_fat_percent: props.latest?.date === props.today ? props.latest.body_fat_percent : '',
    notes: props.latest?.date === props.today ? props.latest.notes : '',
});

const heightForm = useForm({
    height_cm: props.goals?.height_cm ?? '',
});

const hasDelta = computed(() => Boolean(props.delta));
const currentBmi = computed(() => {
    if (!props.latest?.weight_kg || !props.goals?.height_cm) return null;

    const heightMeters = Number(props.goals.height_cm) / 100;

    return (Number(props.latest.weight_kg) / (heightMeters * heightMeters)).toFixed(1);
});
const chartMetrics = computed(() => [...props.history].reverse());
const weightRange = computed(() => rangeFor(chartMetrics.value.map((metric) => metric.weight_kg), props.goals?.target_weight_kg));
const bodyFatRange = computed(() => rangeFor(chartMetrics.value.map((metric) => metric.body_fat_percent).filter((value) => value !== null), props.goals?.target_body_fat_percent));
const weightPoints = computed(() => chartPoints(chartMetrics.value.map((metric) => metric.weight_kg), weightRange.value));
const bodyFatPoints = computed(() => chartPoints(chartMetrics.value.map((metric) => metric.body_fat_percent), bodyFatRange.value));

function deltaLabel(value, suffix) {
    if (value === null || value === undefined) return 'No change';

    const sign = value > 0 ? '+' : '';

    return `${sign}${value}${suffix}`;
}

function save() {
    form.post('/progress/body-metrics', { preserveScroll: true });
}

function saveHeight() {
    heightForm.put('/progress/height', { preserveScroll: true });
}

function rangeFor(values, target = null) {
    const numeric = values.map(Number).filter((value) => Number.isFinite(value));

    if (target !== null && target !== undefined && target !== '') {
        numeric.push(Number(target));
    }

    if (!numeric.length) return { min: 0, max: 1 };

    const min = Math.min(...numeric);
    const max = Math.max(...numeric);
    const padding = Math.max((max - min) * 0.15, 1);

    return { min: min - padding, max: max + padding };
}

function chartPoints(values, range) {
    const numeric = values.map((value) => value === null || value === undefined ? null : Number(value));
    const count = Math.max(numeric.length - 1, 1);

    return numeric
        .map((value, index) => {
            if (value === null || !Number.isFinite(value)) return null;

            const x = (index / count) * 100;
            const y = 100 - (((value - range.min) / (range.max - range.min)) * 100);

            return `${x},${Math.max(0, Math.min(100, y))}`;
        })
        .filter(Boolean)
        .join(' ');
}

function targetY(target, range) {
    if (target === null || target === undefined || target === '') return null;

    return 100 - (((Number(target) - range.min) / (range.max - range.min)) * 100);
}

function removeMetric(metric) {
    if (window.confirm(`Delete progress item from ${metric.date}?`)) {
        router.delete(`/progress/body-metrics/${metric.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Progress" />

    <section class="space-y-5">
        <header>
            <p class="text-sm  text-stone-500">Body metrics</p>
            <h1 class="text-3xl font-semibold tracking-normal text-[#17211b]">Progress</h1>
        </header>

        <article class="grid grid-cols-3 gap-3">
            <Card>
                <p class="text-xs font-semibold uppercase text-stone-500">Weight</p>
                <p class="mt-2 text-3xl font-semibold">{{ latest?.weight_kg ?? '--' }}<span class="text-sm text-stone-500"> kg</span></p>
                <p class="mt-1 flex items-center gap-1 text-sm " :class="delta?.weight_kg > 0 ? 'text-red-700' : 'text-emerald-700'">
                    <component :is="delta?.weight_kg > 0 ? TrendingUp : TrendingDown" v-if="hasDelta" :size="15" />
                    {{ hasDelta ? deltaLabel(delta.weight_kg, ' kg') : 'First entry' }}
                </p>
            </Card>

            <Card>
                <p class="text-xs font-semibold uppercase text-stone-500">Body fat</p>
                <p class="mt-2 text-3xl font-semibold">{{ latest?.body_fat_percent ?? '--' }}<span class="text-sm text-stone-500">%</span></p>
                <p class="mt-1 flex items-center gap-1 text-sm " :class="delta?.body_fat_percent > 0 ? 'text-red-700' : 'text-emerald-700'">
                    <component :is="delta?.body_fat_percent > 0 ? TrendingUp : TrendingDown" v-if="hasDelta && delta.body_fat_percent !== null" :size="15" />
                    {{ hasDelta ? deltaLabel(delta.body_fat_percent, '%') : 'First entry' }}
                </p>
            </Card>

            <Card>
                <p class="text-xs font-semibold uppercase text-stone-500">BMI</p>
                <p class="mt-2 text-3xl font-semibold">{{ currentBmi ?? '--' }}</p>
                <p class="mt-1 text-sm  text-stone-500">{{ goals?.height_cm ? `${goals.height_cm} cm` : 'Set height' }}</p>
            </Card>
        </article>

        <Card v-if="history.length">
            <h2 class="font-semibold">Trends</h2>
            <div class="mt-4 grid gap-5">
                <div>
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase text-stone-500">
                        <span>Weight</span>
                        <span v-if="goals?.target_weight_kg">Goal {{ goals.target_weight_kg }} kg</span>
                    </div>
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full overflow-visible rounded-md bg-stone-50">
                        <line v-if="targetY(goals?.target_weight_kg, weightRange) !== null" x1="0" x2="100" :y1="targetY(goals.target_weight_kg, weightRange)" :y2="targetY(goals.target_weight_kg, weightRange)" stroke="#d28a45" stroke-width="1.5" stroke-dasharray="4 3" />
                        <polyline :points="weightPoints" fill="none" stroke="#253d2c" stroke-width="3" vector-effect="non-scaling-stroke" />
                    </svg>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase text-stone-500">
                        <span>Body fat</span>
                        <span v-if="goals?.target_body_fat_percent">Goal {{ goals.target_body_fat_percent }}%</span>
                    </div>
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full overflow-visible rounded-md bg-stone-50">
                        <line v-if="targetY(goals?.target_body_fat_percent, bodyFatRange) !== null" x1="0" x2="100" :y1="targetY(goals.target_body_fat_percent, bodyFatRange)" :y2="targetY(goals.target_body_fat_percent, bodyFatRange)" stroke="#d28a45" stroke-width="1.5" stroke-dasharray="4 3" />
                        <polyline :points="bodyFatPoints" fill="none" stroke="#b05252" stroke-width="3" vector-effect="non-scaling-stroke" />
                    </svg>
                </div>
            </div>
        </Card>

        <Card>
            <form class="space-y-3" @submit.prevent="saveHeight">
                <h2 class="font-semibold">Height</h2>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-stone-500">Height cm</span>
                    <input
                        v-model.number="heightForm.height_cm"
                        type="number"
                        min="50"
                        max="260"
                        step="0.1"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="heightForm.errors.height_cm" class="mt-1 block text-sm  text-red-700">{{ heightForm.errors.height_cm }}</span>
                </label>
                <button class="w-full rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="heightForm.processing">
                    Save height
                </button>
            </form>
        </Card>

        <Card>
        <form class="space-y-3" @submit.prevent="save">
            <h2 class="font-semibold">Log current</h2>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-stone-500">Date</span>
                <input
                    v-model="form.date"
                    type="date"
                    class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                >
                <span v-if="form.errors.date" class="mt-1 block text-sm  text-red-700">{{ form.errors.date }}</span>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label>
                    <span class="text-xs font-semibold uppercase text-stone-500">Weight kg</span>
                    <input
                        v-model="form.weight_kg"
                        type="number"
                        min="1"
                        max="1000"
                        step="0.1"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="form.errors.weight_kg" class="mt-1 block text-sm  text-red-700">{{ form.errors.weight_kg }}</span>
                </label>

                <label>
                    <span class="text-xs font-semibold uppercase text-stone-500">Body fat %</span>
                    <input
                        v-model="form.body_fat_percent"
                        type="number"
                        min="1"
                        max="80"
                        step="0.1"
                        class="mt-1 w-full rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                    >
                    <span v-if="form.errors.body_fat_percent" class="mt-1 block text-sm  text-red-700">{{ form.errors.body_fat_percent }}</span>
                </label>
            </div>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-stone-500">Notes</span>
                <textarea
                    v-model="form.notes"
                    rows="3"
                    class="mt-1 w-full resize-none rounded-md border border-stone-200 bg-stone-50 px-3 py-3  outline-none focus:border-[#6f9b58]"
                />
                <span v-if="form.errors.notes" class="mt-1 block text-sm  text-red-700">{{ form.errors.notes }}</span>
            </label>

            <button class="w-full rounded-md bg-[#253d2c] px-4 py-3 font-semibold text-white active:bg-[#17211b]" :disabled="form.processing">
                Save progress
            </button>
        </form>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Recent history</h2>

            <Card v-if="history.length" class="divide-y divide-stone-100">
                <div v-for="metric in history" :key="metric.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold">{{ metric.date }}</p>
                        <p class="text-sm  text-stone-500">
                            {{ metric.weight_kg }} kg
                            <span v-if="metric.body_fat_percent !== null"> · {{ metric.body_fat_percent }}%</span>
                        </p>
                    </div>
                    <p v-if="metric.notes" class="mt-1 text-sm text-stone-500">{{ metric.notes }}</p>
                    </div>
                    <button class="rounded p-2 text-stone-400 active:bg-stone-100" aria-label="Remove progress item" @click="removeMetric(metric)">
                        <Trash2 :size="18" />
                    </button>
                </div>
            </Card>

            <Card v-else class="text-sm  text-stone-500">No progress entries yet.</Card>
        </section>
    </section>
</template>
