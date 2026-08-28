<script setup lang="ts">
import { CurveType } from '@unovis/ts';
import { VisAxis, VisLine, VisScatter, VisXYContainer } from '@unovis/vue';
import { computed } from 'vue';
import { formatChartTickDate, formatChartTooltipDate } from '../dateFormat';
import { chartYDomain, type TrendChartRow } from '../progressChart';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
    type ChartConfig,
} from './ui/chart';

const props = withDefaults(defineProps<{
    data: TrendChartRow[];
    config: ChartConfig;
    xDomain: [number, number];
    lines: string[];
    dashed?: string[];
    dots?: string[];
    valueSuffix?: string;
}>(), {
    dashed: () => [],
    dots: () => [],
    valueSuffix: '',
});

const seriesValues = computed(() => props.data.flatMap((row) => (
    props.lines
        .filter((key) => key !== 'goal')
        .map((key) => row[key as keyof TrendChartRow])
        .filter((value): value is number => typeof value === 'number')
)));
const goalValue = computed(() => {
    const goal = props.data.find((row) => typeof row.goal === 'number')?.goal;

    return goal ?? null;
});
const yDomain = computed(() => {
    const domain = chartYDomain(seriesValues.value, goalValue.value);

    return domain[0] === undefined ? undefined : domain;
});
const chartKey = computed(() => `${props.xDomain[0]}-${props.xDomain[1]}-${props.data.length}-${yDomain.value?.[0]}`);
const x = (row: TrendChartRow) => row.date.getTime();
const yFor = (key: string) => (row: TrendChartRow) => {
    const value = row[key as keyof TrendChartRow];

    return typeof value === 'number' ? value : undefined;
};

const dashedKeys = computed(() => props.dashed.filter((key) => props.lines.includes(key)));
const solidKeys = computed(() => props.lines.filter((key) => !dashedKeys.value.includes(key) && key !== 'goal'));
const measuredKeys = computed(() => props.lines.filter((key) => key !== 'goal'));
const goalLineData = computed(() => {
    if (!dashedKeys.value.includes('goal') || goalValue.value === null) {
        return [];
    }

    return [
        { date: new Date(props.xDomain[0]), goal: goalValue.value },
        { date: new Date(props.xDomain[1]), goal: goalValue.value },
    ];
});
const tooltipConfig = computed(() => Object.fromEntries(
    Object.entries(props.config).filter(([key]) => key !== 'goal'),
) as ChartConfig);
const solidY = computed(() => solidKeys.value.map(yFor));
const dashedY = computed(() => dashedKeys.value.map(yFor));
const solidColors = computed(() => solidKeys.value.map((key) => props.config[key]?.color ?? 'currentColor'));
const dashedColors = computed(() => dashedKeys.value.map((key) => props.config[key]?.color ?? 'currentColor'));
const crosshairY = computed(() => measuredKeys.value.map(yFor));
const crosshairColors = computed(() => measuredKeys.value.map((key) => props.config[key]?.color ?? 'currentColor'));
const tooltipRoot = typeof document === 'undefined' ? undefined : document.body;
const tooltipTemplate = computed(() => componentToString(tooltipConfig.value, ChartTooltipContent, {
    labelFormatter: formatChartTooltipDate,
    valueSuffix: props.valueSuffix,
}));

function colorFor(key: string): string {
    return props.config[key]?.color ?? 'currentColor';
}

function formatYTick(value: number | Date): string {
    return Number(value).toLocaleString(undefined, { maximumFractionDigits: 1 });
}
</script>

<template>
    <ChartContainer :config="config" class="aspect-auto h-48 w-full" cursor>
        <VisXYContainer
            :key="chartKey"
            :data="data"
            :x-domain="xDomain"
            :y-domain="yDomain"
            :duration="0"
            :padding="{ top: 8, right: 8 }"
        >
            <VisLine
                v-if="solidKeys.length"
                :x="x"
                :y="solidY"
                :color="solidColors"
                :line-width="2"
                :curve-type="CurveType.Linear"
            />
            <VisLine
                v-if="goalLineData.length"
                :data="goalLineData"
                :x="x"
                :y="dashedY"
                :color="dashedColors"
                :line-width="1.5"
                :line-dash-array="[4, 3]"
                :curve-type="CurveType.Linear"
            />
            <VisScatter
                v-for="key in dots"
                :key="key"
                :x="x"
                :y="yFor(key)"
                :size="6"
                :color="colorFor(key)"
            />
            <VisAxis
                type="x"
                :tick-line="false"
                :domain-line="false"
                :grid-line="false"
                :num-ticks="4"
                :tick-text-font-size="'11px'"
                :tick-format="formatChartTickDate"
            />
            <VisAxis
                type="y"
                :tick-line="false"
                :domain-line="false"
                :grid-line="true"
                :num-ticks="4"
                :tick-text-font-size="'11px'"
                :tick-format="formatYTick"
            />
            <ChartTooltip :container="tooltipRoot" />
            <ChartCrosshair
                :x="x"
                :y="crosshairY"
                :color="crosshairColors"
                :template="tooltipTemplate"
                :hide-when-far-from-pointer="false"
            />
        </VisXYContainer>
    </ChartContainer>
</template>
