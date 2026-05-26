export type Appearance = 'light' | 'dark' | 'system';

const appearanceStorageKey = 'buff.appearance';

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

export function watchSystemAppearance(): void {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedAppearance() === 'system') {
            applyAppearance('system');
        }
    });
}
