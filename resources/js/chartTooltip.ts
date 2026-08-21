export type TooltipSeriesConfig = Record<string, {
    label?: unknown;
    color?: string;
} | undefined>;

export type TooltipSeriesEntry = {
    key: string;
    value: number;
    itemConfig: NonNullable<TooltipSeriesConfig[string]>;
    indicatorColor: string | undefined;
};

export function tooltipLabelDate(payload: Record<string, unknown>, x?: number | Date): number | Date | undefined {
    const date = payload.date;

    if (date instanceof Date) {
        return date;
    }

    if (typeof date === 'number') {
        return date;
    }

    return x;
}

export function unwrapTooltipDatum(input: unknown): Record<string, unknown> {
    if (input == null || typeof input !== 'object') {
        return {};
    }

    if (
        'data' in input
        && input.data != null
        && typeof input.data === 'object'
        && !Array.isArray(input.data)
        && !(input.data instanceof Date)
    ) {
        return input.data as Record<string, unknown>;
    }

    return input as Record<string, unknown>;
}

export function tooltipSeriesEntries(
    payload: Record<string, unknown>,
    config: TooltipSeriesConfig,
): TooltipSeriesEntry[] {
    return Object.entries(payload).flatMap(([key, value]) => {
        const itemConfig = config[key];

        if (!itemConfig || typeof value !== 'number') {
            return [];
        }

        return [{
            key,
            value,
            itemConfig,
            indicatorColor: itemConfig.color,
        }];
    });
}
