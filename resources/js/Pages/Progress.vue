<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Trash2, TrendingDown, TrendingUp } from '@lucide/vue';
import Card from "../Components/Card.vue";
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Textarea from '../Components/ui/textarea/Textarea.vue';

interface BodyMetric {
    id: number;
    date: string;
    weight_kg: number;
    body_fat_percent: number | null;
    notes: string | null;
}

interface BodyGoals {
    height_cm: number | null;
    target_weight_kg: number | null;
    target_body_fat_percent: number | null;
}

interface BodyDelta {
    weight_kg: number;
    body_fat_percent: number | null;
}

interface ChartRange {
    min: number;
    max: number;
}

const props = withDefaults(defineProps<{
    today: string;
    latest?: BodyMetric | null;
    goals?: BodyGoals | null;
    delta?: BodyDelta | null;
    history: BodyMetric[];
}>(), {
    latest: null,
    goals: null,
    delta: null,
});

const form = useForm({
    date: props.today,
    weight_kg: props.latest?.date === props.today ? props.latest.weight_kg : '',
    body_fat_percent: props.latest?.date === props.today ? props.latest.body_fat_percent : '',
    notes: props.latest?.date === props.today ? props.latest.notes : '',
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

function deltaLabel(value: number | null | undefined, suffix: string) {
    if (value === null || value === undefined) return 'No change';

    const sign = value > 0 ? '+' : '';

    return `${sign}${value}${suffix}`;
}

function save() {
    form.post('/progress/body-metrics', { preserveScroll: true });
}

function rangeFor(values: Array<number | null>, target: number | null = null): ChartRange {
    const numeric = values.map(Number).filter((value) => Number.isFinite(value));

    if (target !== null && target !== undefined) {
        numeric.push(Number(target));
    }

    if (!numeric.length) return { min: 0, max: 1 };

    const min = Math.min(...numeric);
    const max = Math.max(...numeric);
    const padding = Math.max((max - min) * 0.15, 1);

    return { min: min - padding, max: max + padding };
}

function chartPoints(values: Array<number | null>, range: ChartRange): string {
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

function targetY(target: number | null | undefined, range: ChartRange): number | null {
    if (target === null || target === undefined) return null;

    return 100 - (((Number(target) - range.min) / (range.max - range.min)) * 100);
}

function removeMetric(metric: BodyMetric) {
    if (window.confirm(`Delete progress item from ${metric.date}?`)) {
        router.delete(`/progress/body-metrics/${metric.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Progress" />

    <section class="space-y-5">
        <header>
            <p class="text-sm  text-muted-foreground">Body metrics</p>
            <h1 class="text-3xl font-semibold tracking-normal text-foreground">Progress</h1>
        </header>

        <article class="grid grid-cols-3 gap-3">
            <Card>
                <p class="text-xs font-semibold uppercase text-muted-foreground">Weight</p>
                <p class="mt-2 text-3xl font-semibold">{{ latest?.weight_kg ?? '--' }}<span class="text-sm text-muted-foreground"> kg</span></p>
                <p class="mt-1 flex items-center gap-1 text-sm " :class="delta?.weight_kg > 0 ? 'text-destructive' : 'text-success-foreground'">
                    <component :is="delta?.weight_kg > 0 ? TrendingUp : TrendingDown" v-if="hasDelta" :size="15" />
                    {{ hasDelta ? deltaLabel(delta.weight_kg, ' kg') : 'First entry' }}
                </p>
            </Card>

            <Card>
                <p class="text-xs font-semibold uppercase text-muted-foreground">Body fat</p>
                <p class="mt-2 text-3xl font-semibold">{{ latest?.body_fat_percent ?? '--' }}<span class="text-sm text-muted-foreground">%</span></p>
                <p class="mt-1 flex items-center gap-1 text-sm " :class="delta?.body_fat_percent > 0 ? 'text-destructive' : 'text-success-foreground'">
                    <component :is="delta?.body_fat_percent > 0 ? TrendingUp : TrendingDown" v-if="hasDelta && delta.body_fat_percent !== null" :size="15" />
                    {{ hasDelta ? deltaLabel(delta.body_fat_percent, '%') : 'First entry' }}
                </p>
            </Card>

            <Card>
                <p class="text-xs font-semibold uppercase text-muted-foreground">BMI</p>
                <p class="mt-2 text-3xl font-semibold">{{ currentBmi ?? '--' }}</p>
                <p class="mt-1 text-sm  text-muted-foreground">{{ goals?.height_cm ? `${goals.height_cm} cm` : 'Set height' }}</p>
            </Card>
        </article>

        <Card v-if="history.length">
            <h2 class="font-semibold">Trends</h2>
            <div class="mt-4 grid gap-5">
                <div>
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase text-muted-foreground">
                        <span>Weight</span>
                        <span v-if="goals?.target_weight_kg">Goal {{ goals.target_weight_kg }} kg</span>
                    </div>
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full overflow-visible rounded-md bg-muted">
                        <line v-if="targetY(goals?.target_weight_kg, weightRange) !== null" x1="0" x2="100" :y1="targetY(goals.target_weight_kg, weightRange)" :y2="targetY(goals.target_weight_kg, weightRange)" stroke="var(--food)" stroke-width="1.5" stroke-dasharray="4 3" />
                        <polyline :points="weightPoints" fill="none" stroke="var(--primary)" stroke-width="3" vector-effect="non-scaling-stroke" />
                    </svg>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between text-xs font-semibold uppercase text-muted-foreground">
                        <span>Body fat</span>
                        <span v-if="goals?.target_body_fat_percent">Goal {{ goals.target_body_fat_percent }}%</span>
                    </div>
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="h-32 w-full overflow-visible rounded-md bg-muted">
                        <line v-if="targetY(goals?.target_body_fat_percent, bodyFatRange) !== null" x1="0" x2="100" :y1="targetY(goals.target_body_fat_percent, bodyFatRange)" :y2="targetY(goals.target_body_fat_percent, bodyFatRange)" stroke="var(--food)" stroke-width="1.5" stroke-dasharray="4 3" />
                        <polyline :points="bodyFatPoints" fill="none" stroke="var(--fat)" stroke-width="3" vector-effect="non-scaling-stroke" />
                    </svg>
                </div>
            </div>
        </Card>

        <Card>
        <form class="space-y-3" @submit.prevent="save">
            <h2 class="font-semibold">Log current</h2>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-muted-foreground">Date</span>
                <Input
                    v-model="form.date"
                    type="date"
                    class="mt-1"
                />
                <span v-if="form.errors.date" class="mt-1 block text-sm  text-destructive">{{ form.errors.date }}</span>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label>
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Weight kg</span>
                    <Input
                        v-model="form.weight_kg"
                        type="number"
                        min="1"
                        max="1000"
                        step="0.1"
                        class="mt-1"
                    />
                    <span v-if="form.errors.weight_kg" class="mt-1 block text-sm  text-destructive">{{ form.errors.weight_kg }}</span>
                </label>

                <label>
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Body fat %</span>
                    <Input
                        v-model="form.body_fat_percent"
                        type="number"
                        min="1"
                        max="80"
                        step="0.1"
                        class="mt-1"
                    />
                    <span v-if="form.errors.body_fat_percent" class="mt-1 block text-sm  text-destructive">{{ form.errors.body_fat_percent }}</span>
                </label>
            </div>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-muted-foreground">Notes</span>
                <Textarea
                    v-model="form.notes"
                    rows="3"
                    class="mt-1"
                ></Textarea>
                <span v-if="form.errors.notes" class="mt-1 block text-sm  text-destructive">{{ form.errors.notes }}</span>
            </label>

            <Button class="w-full" :disabled="form.processing">
                Save progress
            </Button>
        </form>
        </Card>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Recent history</h2>

            <Card v-if="history.length" class="divide-y divide-border/70">
                <div v-for="metric in history" :key="metric.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold">{{ metric.date }}</p>
                        <p class="text-sm  text-muted-foreground">
                            {{ metric.weight_kg }} kg
                            <span v-if="metric.body_fat_percent !== null"> · {{ metric.body_fat_percent }}%</span>
                        </p>
                    </div>
                    <p v-if="metric.notes" class="mt-1 text-sm text-muted-foreground">{{ metric.notes }}</p>
                    </div>
                    <Button variant="ghost" size="icon" class="text-muted-foreground/70" aria-label="Remove progress item" @click="removeMetric(metric)">
                        <Trash2 :size="18" />
                    </Button>
                </div>
            </Card>

            <Card v-else class="text-sm  text-muted-foreground">No progress entries yet.</Card>
        </section>
    </section>
</template>
