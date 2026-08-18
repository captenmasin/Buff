export type DayStatus = 'target' | 'under' | 'over' | 'neutral';

export function dayStatusLabel(status: DayStatus): string {
    return {
        target: 'on target',
        under: 'under target',
        over: 'over target',
        neutral: 'no log',
    }[status];
}

export function dayStatusClass(status: DayStatus): string {
    return {
        target: 'bg-success',
        under: 'border-2 border-warning bg-transparent',
        over: 'bg-fat',
        neutral: 'border border-muted-foreground/50 bg-transparent',
    }[status];
}
