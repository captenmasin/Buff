export function publicAssetUrl(
    path: string,
    baseUrl = import.meta.env.BASE_URL,
    protocol = globalThis.location?.protocol,
): string {
    const normalizedPath = path.replace(/^\/+/, '');
    const prefix = protocol === 'php:' && baseUrl.startsWith('/_assets/') ? '/_assets/' : '/';

    return `${prefix}${normalizedPath}`;
}
