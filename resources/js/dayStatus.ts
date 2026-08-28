export type DayStatus = 'target' | 'under' | 'over' | 'neutral';
export type DayStatusIcon = 'check' | 'minus' | 'plus';

export function dayStatusLabel(status: DayStatus): string {
    return {
        target: 'on target',
        under: 'under target',
        over: 'over target',
        neutral: 'no log',
    }[status];
}

export function dayStatusHasTick(status: DayStatus): boolean {
    return status === 'target';
}

export function dayStatusIcon(status: DayStatus): DayStatusIcon | null {
    return {
        target: 'check',
        under: 'minus',
        over: 'plus',
        neutral: null,
    }[status];
}

export function dayStatusClass(status: DayStatus): string {
    return {
        target: 'bg-success text-white',
        under: 'bg-warning-soft text-warning-soft-foreground',
        over: 'bg-danger-soft text-danger-soft-foreground',
        neutral: 'border border-muted-foreground/50 bg-transparent',
    }[status];
}
