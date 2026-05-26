export type WeightUnit = 'kg' | 'lb';
export type HeightUnit = 'cm' | 'in';

function roundBodyValue(value: number): number {
    return Number(value.toFixed(1));
}

export function weightFromKg(value: number | null | undefined, unit: WeightUnit): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    return roundBodyValue(unit === 'lb' ? Number(value) * 2.2046226218 : Number(value));
}

export function weightToKg(value: number | string | null | undefined, unit: WeightUnit): number | string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return roundBodyValue(unit === 'lb' ? Number(value) / 2.2046226218 : Number(value));
}

export function heightFromCm(value: number | null | undefined, unit: HeightUnit): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    return roundBodyValue(unit === 'in' ? Number(value) / 2.54 : Number(value));
}

export function heightToCm(value: number | string | null | undefined, unit: HeightUnit): number | string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return roundBodyValue(unit === 'in' ? Number(value) * 2.54 : Number(value));
}

export function formatBodyValue(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '--';
    }

    return Number(value).toLocaleString(undefined, {
        maximumFractionDigits: 1,
    });
}
