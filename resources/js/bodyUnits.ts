export type WeightUnit = 'kg' | 'lb';
export type HeightUnit = 'cm' | 'in';
export type MeasurementUnit = 'cm' | 'in';
type FeetAndInches = { feet: number | ''; inches: number | '' };

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

export function measurementFromCm(value: number | null | undefined, unit: MeasurementUnit): number | null {
    return heightFromCm(value, unit);
}

export function measurementToCm(value: number | string | null | undefined, unit: MeasurementUnit): number | string {
    return heightToCm(value, unit);
}

export function feetAndInchesFromInches(value: number | string | null | undefined): FeetAndInches {
    if (value === null || value === undefined || value === '') {
        return { feet: '', inches: '' };
    }

    const feet = Math.floor(Number(value) / 12);

    return { feet, inches: roundBodyValue(Number(value) - feet * 12) };
}

export function inchesFromFeetAndInches(feet: number | string, inches: number | string): number | '' {
    if (feet === '' && inches === '') {
        return '';
    }

    return roundBodyValue(Number(feet || 0) * 12 + Number(inches || 0));
}

export function formatBodyValue(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '--';
    }

    return Number(value).toLocaleString(undefined, {
        maximumFractionDigits: 1,
    });
}
