const SETTINGS_HUB = '/settings';

export type SettingsNavDirection = 'forward' | 'back';

export function settingsPathname(href: string): string {
    try {
        return new URL(href, 'https://buff.test').pathname;
    } catch {
        return href.split('?')[0] ?? href;
    }
}

export function settingsStackDepth(pathname: string): number {
    if (pathname === SETTINGS_HUB) {
        return 1;
    }

    if (pathname.startsWith(`${SETTINGS_HUB}/`)) {
        return 2;
    }

    return 0;
}

export function settingsNavDirection(fromHref: string, toHref: string): SettingsNavDirection | null {
    const from = settingsPathname(fromHref);
    const to = settingsPathname(toHref);

    if (from === to) {
        return null;
    }

    const fromDepth = settingsStackDepth(from);
    const toDepth = settingsStackDepth(to);

    if (fromDepth === 0 || toDepth === 0) {
        return null;
    }

    if (toDepth === fromDepth) {
        return 'forward';
    }

    return toDepth > fromDepth ? 'forward' : 'back';
}

export function settingsVisitOptions(href: string): {viewTransition: true} | Record<string, never> {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return {};
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return {};
    }

    if (!window.matchMedia('(max-width: 39.999rem)').matches) {
        return {};
    }

    const direction = settingsNavDirection(window.location.href, href);

    if (direction === null) {
        delete document.documentElement.dataset.settingsNav;

        return {};
    }

    document.documentElement.dataset.settingsNav = direction;

    return {viewTransition: true};
}
