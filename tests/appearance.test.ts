import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {shouldReduceMotion} from '../resources/js/appearance.ts';

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
