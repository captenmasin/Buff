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

function parseLocalDate(value: string): Date {
    const [year, month, day] = String(value).split('-').map(Number);

    if (!year || !month || !day) {
        return new Date(Number.NaN);
    }

    return new Date(year, month - 1, day);
}

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
