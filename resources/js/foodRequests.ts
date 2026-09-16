export function foodSearchUrl(query: string, locale: string): string {
    const params = new URLSearchParams({q: query, locale});

    return `/food-products/search?${params.toString()}`;
}

export function responseErrorMessage(error: unknown, field: string, fallback: string): string {
    if (!error || typeof error !== 'object' || !('response' in error)) {
        return fallback;
    }

    let data = (error as {response?: {data?: unknown}}).response?.data;

    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch {
            return fallback;
        }
    }

    if (!data || typeof data !== 'object') {
        return fallback;
    }

    const payload = data as {errors?: Record<string, unknown>; message?: unknown};
    const fieldError = payload.errors?.[field];

    if (Array.isArray(fieldError) && typeof fieldError[0] === 'string') {
        return fieldError[0];
    }

    if (typeof fieldError === 'string') {
        return fieldError;
    }

    return typeof payload.message === 'string' && payload.message !== '' ? payload.message : fallback;
}
