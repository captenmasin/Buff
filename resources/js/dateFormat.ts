type FormatDisplayDateOptions = {
    weekday?: 'short' | 'long';
    year?: boolean;
};

export function parseLocalDate(value: string): Date {
    const [year, month, day] = String(value).split('-').map(Number);

    if (!year || !month || !day) {
        return new Date(Number.NaN);
    }

    return new Date(year, month - 1, day);
}

export function formatDisplayDate(value: string, options: FormatDisplayDateOptions = {}): string {
    const date = parseLocalDate(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        weekday: options.weekday ?? 'long',
        day: 'numeric',
        month: 'long',
        year: options.year === false ? undefined : 'numeric',
    }).format(date);
}

const chartTickFormatter = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
});

const chartTooltipFormatter = new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});

function toDate(value: number | Date): Date {
    return value instanceof Date ? value : new Date(value);
}

export function formatChartTickDate(value: number | Date): string {
    return chartTickFormatter.format(toDate(value));
}

export function formatChartTooltipDate(value: number | Date): string {
    return chartTooltipFormatter.format(toDate(value));
}
