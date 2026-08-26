export function publicAssetUrl(
    path: string,
    protocol = globalThis.location?.protocol,
): string {
    const normalizedPath = path.replace(/^\/+/, '');
    const prefix = protocol === 'php:' ? '/_assets/' : '/';

    return `${prefix}${normalizedPath}`;
}
