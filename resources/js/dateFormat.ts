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

export function formatDisplayDate(value: string, options: FormatDisplayDateOptions = {}): string {
    const [year, month, day] = String(value).split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    return (options.weekday === 'short' ? shortDayDateFormatter : dateFormatter).format(new Date(year, month - 1, day));
}
