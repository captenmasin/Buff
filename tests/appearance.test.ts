import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {shouldReduceMotion} from '../resources/js/appearance.ts';

const appearancePageSource = readFileSync(new URL('../resources/js/Pages/Settings/Appearance.vue', import.meta.url), 'utf8');
const weeklyPageSource = readFileSync(new URL('../resources/js/Pages/Weekly.vue', import.meta.url), 'utf8');

test('reduces motion when requested by the user or their device', () => {
    assert.equal(shouldReduceMotion(false, false), false);
    assert.equal(shouldReduceMotion(true, false), true);
    assert.equal(shouldReduceMotion(false, true), true);
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
