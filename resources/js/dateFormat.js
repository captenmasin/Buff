const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

export function formatDisplayDate(value) {
    const [year, month, day] = String(value).split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    return dateFormatter.format(new Date(year, month - 1, day));
}
