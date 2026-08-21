const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const shortDayDateFormatter = new Intl.DateTimeFormat('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

type FormatDisplayDateOptions = {
    weekday?: 'short' | 'long';
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

    return (options.weekday === 'short' ? shortDayDateFormatter : dateFormatter).format(date);
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
