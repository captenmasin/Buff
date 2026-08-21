export type DayStatus = 'target' | 'under' | 'over' | 'neutral';

export function dayStatusLabel(status: DayStatus): string {
    return {
        target: 'on target',
        under: 'under target',
        over: 'over target',
        neutral: 'no log',
    }[status];
}

export function dayStatusHasTick(status: DayStatus): boolean {
    return status !== 'neutral';
}

export function dayStatusClass(status: DayStatus): string {
    return {
        target: 'bg-success text-white',
        under: 'bg-warning text-white',
        over: 'bg-fat text-white',
        neutral: 'border border-muted-foreground/50 bg-transparent',
    }[status];
}
