import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {applyAppearance, shouldReduceMotion} from '../resources/js/appearance.ts';

const appearancePageSource = readFileSync(new URL('../resources/js/Pages/Settings/Appearance.vue', import.meta.url), 'utf8');
const weeklyPageSource = readFileSync(new URL('../resources/js/Pages/Weekly.vue', import.meta.url), 'utf8');

test('matches browser chrome and native controls to the selected appearance', (context) => {
    const originals = ['window', 'document'].map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)] as const);
    let dark = false;
    let systemDark = false;
    let stored = 'system';
    const metas = [{content: ''}, {content: ''}];
    const root = {
        classList: {toggle: (_name: string, enabled: boolean) => { dark = enabled; }},
        style: {colorScheme: ''},
    };

    context.after(() => {
        for (const [key, descriptor] of originals) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else Reflect.deleteProperty(globalThis, key);
        }
    });
    Object.defineProperty(globalThis, 'document', {configurable: true, value: {
        documentElement: root,
        querySelectorAll: () => metas,
    }});
    Object.defineProperty(globalThis, 'window', {configurable: true, value: {
        localStorage: {getItem: () => stored},
        matchMedia: () => ({matches: systemDark}),
        getComputedStyle: () => ({backgroundColor: dark ? 'rgb(21, 22, 22)' : 'rgb(245, 246, 246)'}),
    }});

    for (const [appearance, prefersDark, expectedScheme, expectedColor] of [
        ['system', false, 'light', 'rgb(245, 246, 246)'],
        ['system', true, 'dark', 'rgb(21, 22, 22)'],
        ['light', true, 'light', 'rgb(245, 246, 246)'],
        ['dark', false, 'dark', 'rgb(21, 22, 22)'],
    ] as const) {
        stored = appearance;
        systemDark = prefersDark;

        applyAppearance();

        assert.equal(root.style.colorScheme, expectedScheme);
        assert.deepEqual(metas.map((meta) => meta.content), [expectedColor, expectedColor]);
    }
});

test('reduces motion when requested by the user or their device', () => {
    assert.equal(shouldReduceMotion(false, false), false);
    assert.equal(shouldReduceMotion(true, false), true);
    assert.equal(shouldReduceMotion(false, true), true);
});

test('rechecks the Android system motion setting when the app resumes', () => {
    const appShellSource = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.match(appShellSource, /import \{applyReducedMotion} from '\.\.\/appearance'/);
    assert.match(appShellSource, /async function syncOnResume\(\) \{\s+applyReducedMotion\(\)/);
});

test('uses a dark grey page fill in dark mode', () => {
    const styles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');

    assert.match(styles, /--brand-charcoal: #151616;/);
    assert.match(styles, /\.dark \{[\s\S]*?--background: var\(--brand-charcoal\);/);
});

test('matches the site segmented-control styling for theme and roundup choices', () => {
    const weeklyModeSelector = weeklyPageSource.match(/<div class="grid grid-cols-2 gap-1 rounded-xl[\s\S]*?<form/)?.[0] ?? '';

    assert.match(appearancePageSource, /bg-card text-foreground shadow-sm hover:bg-card/);
    assert.doesNotMatch(appearancePageSource, /bg-primary-container text-primary-container-foreground/);
    assert.equal(weeklyModeSelector.match(/bg-card text-foreground shadow-sm hover:bg-card/g)?.length, 2);
    assert.doesNotMatch(weeklyModeSelector, /bg-primary-container text-primary-container-foreground/);
});

test('labels weekly targets, selected days, and range errors accurately', () => {
    assert.match(weeklyPageSource, /kcal period target/);
    assert.match(weeklyPageSource, /Targets use your current goal and update when that goal changes\./);
    assert.match(weeklyPageSource, /:aria-current="day\.is_selected \? 'date' : undefined"/);
    assert.match(weeklyPageSource, /page\.props\.errors\?\.start_date/);
    assert.match(weeklyPageSource, /page\.props\.errors\?\.end_date/);
});
