import { formatChartTickDate, parseLocalDate } from './dateFormat.ts';

export type TrendChartRow = {
    date: Date;
    weight?: number;
    bodyFat?: number;
    goal?: number;
};

export type WeightChartInput = {
    date: string;
    weight: number;
};

export type BodyFatChartInput = {
    date: string;
    bodyFat: number | null;
};

function attachGoal(rows: TrendChartRow[], goal: number | null): TrendChartRow[] {
    if (goal === null) {
        return rows;
    }

    return rows.map((row) => ({ ...row, goal }));
}

export function buildGoalLine(rangeStart: string, rangeEnd: string, goal: number | null): TrendChartRow[] {
    if (goal === null) {
        return [];
    }

    return [
        { date: parseLocalDate(rangeStart), goal },
        { date: parseLocalDate(rangeEnd), goal },
    ];
}

export function chartXDomain(rangeStart: string, rangeEnd: string): [number, number] {
    return [parseLocalDate(rangeStart).getTime(), parseLocalDate(rangeEnd).getTime()];
}

export function chartTickValues(domain: [number, number]): number[] {
    const start = new Date(Math.min(...domain));
    const end = new Date(Math.max(...domain));
    const calendarDays = Math.round((
        Date.UTC(end.getFullYear(), end.getMonth(), end.getDate())
        - Date.UTC(start.getFullYear(), start.getMonth(), start.getDate())
    ) / 86_400_000);
    const tickCount = Math.min(4, calendarDays + 1);

    return Array.from({ length: tickCount }, (_, index) => new Date(
        start.getFullYear(),
        start.getMonth(),
        start.getDate() + Math.round(index * calendarDays / Math.max(1, tickCount - 1)),
    ).getTime());
}

export function chartTickFormatter(domain: [number, number]): (value: number | Date) => string {
    if (new Date(domain[0]).getFullYear() === new Date(domain[1]).getFullYear()) {
        return formatChartTickDate;
    }

    const formatter = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

    return (value) => formatter.format(value);
}

export function chartYDomain(values: number[], goal: number | null): [number | undefined, number | undefined] {
    if (goal === null) {
        return [undefined, undefined];
    }

    const lowest = values.length > 0 ? Math.min(...values, goal) : goal;
    const highest = values.length > 0 ? Math.max(...values, goal) : goal;
    const span = highest - lowest;
    const padding = Math.max(span * 0.12, Math.abs(goal) * 0.03, 1);

    return [Math.max(0, lowest - padding), undefined];
}

export function buildWeightChartData(
    metrics: WeightChartInput[],
    rangeStart: string,
    rangeEnd: string,
    targetWeight: number | null,
): TrendChartRow[] {
    const rows = [...metrics]
        .sort((left, right) => left.date.localeCompare(right.date))
        .map((metric) => ({
            date: parseLocalDate(metric.date),
            weight: metric.weight,
        }));

    return attachGoal(rows, targetWeight);
}

export function buildBodyFatChartData(
    metrics: BodyFatChartInput[],
    rangeStart: string,
    rangeEnd: string,
    targetBodyFat: number | null,
): TrendChartRow[] {
    const rows = [...metrics]
        .sort((left, right) => left.date.localeCompare(right.date))
        .flatMap((metric) => {
            if (metric.bodyFat === null) {
                return [];
            }

            return [{
                date: parseLocalDate(metric.date),
                bodyFat: metric.bodyFat,
            }];
        });

    return attachGoal(rows, targetBodyFat);
}

export function chartSummary(
    rows: TrendChartRow[],
    key: 'weight' | 'bodyFat',
    unit: string,
    goal: number | null,
): string {
    const values = rows
        .map((row) => row[key])
        .filter((value): value is number => typeof value === 'number');

    if (values.length === 0) {
        return '';
    }

    const start = values[0];
    const end = values[values.length - 1];

    if (goal === null) {
        return `Started at ${start}${unit}, now ${end}${unit}.`;
    }

    return `Started at ${start}${unit}, now ${end}${unit}, vs ${goal}${unit} goal.`;
}

export function deltaTone(
    delta: number | null | undefined,
    current: number | null | undefined,
    target: number | null | undefined,
): string {
    if (delta === null || delta === undefined || Math.abs(delta) < 0.05 || current === null || current === undefined || target === null || target === undefined) {
        return 'text-foreground';
    }

    if (Math.abs(target - current) < 0.05) {
        return 'text-foreground';
    }

    const towardsTarget = (target < current && delta < 0) || (target > current && delta > 0);

    return towardsTarget ? 'text-success-foreground' : 'text-destructive';
}
