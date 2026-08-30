import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../resources/js/Components/Add/AddChooser.vue', import.meta.url), 'utf8');

test('renders compact descriptions for every add choice', () => {
    assert.deepEqual(
        [...source.matchAll(/description: '([^']+)'/g)].slice(0, 4).map(([, description]) => description),
        ['Food & history', 'Barcode', 'Macro estimate', 'Manual entry'],
    );
    assert.equal(source.match(/\{\{ choice\.description \}\}/g)?.length, 2);
    assert.equal(source.match(/variant="outline"/g)?.length, 2);
    assert.match(source, /class="block truncate text-xs leading-snug text-muted-foreground"/);
    assert.doesNotMatch(source, /min-h-28|bg-secondary\/70/);
});

test('marks photo analysis as Buff+ when the cached server expiry is inactive', () => {
    assert.match(source, /Buff\+ required/);
    assert.match(source, /LockKeyhole/);
    assert.match(source, /subscriptionActive/);
});
