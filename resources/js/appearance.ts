export type Appearance = 'light' | 'dark' | 'system';

const appearanceStorageKey = 'buff.appearance';
const reduceMotionStorageKey = 'buff.reduceMotion';

export function storedAppearance(): Appearance {
    const value = window.localStorage.getItem(appearanceStorageKey);

    return value === 'light' || value === 'dark' || value === 'system' ? value : 'system';
}

export function applyAppearance(appearance: Appearance = storedAppearance()): void {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const shouldUseDark = appearance === 'dark' || (appearance === 'system' && prefersDark);

    document.documentElement.classList.toggle('dark', shouldUseDark);
}

export function saveAppearance(appearance: Appearance): void {
    window.localStorage.setItem(appearanceStorageKey, appearance);
    applyAppearance(appearance);
}

export function storedReducedMotion(): boolean {
    return window.localStorage.getItem(reduceMotionStorageKey) === 'true';
}

export function shouldReduceMotion(reduceMotion: boolean, systemPrefersReducedMotion: boolean): boolean {
    return reduceMotion || systemPrefersReducedMotion;
}

export function applyReducedMotion(reduceMotion: boolean = storedReducedMotion()): void {
    document.documentElement.toggleAttribute(
        'data-reduce-motion',
        shouldReduceMotion(reduceMotion, window.matchMedia('(prefers-reduced-motion: reduce)').matches),
    );
}

export function saveReducedMotion(reduceMotion: boolean): void {
    if (reduceMotion) {
        window.localStorage.setItem(reduceMotionStorageKey, 'true');
    } else {
        window.localStorage.removeItem(reduceMotionStorageKey);
    }

    applyReducedMotion(reduceMotion);
}

export function watchSystemAppearance(): void {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedAppearance() === 'system') {
            applyAppearance('system');
        }
    });
}

export function watchSystemReducedMotion(): void {
    window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', () => applyReducedMotion());
}
