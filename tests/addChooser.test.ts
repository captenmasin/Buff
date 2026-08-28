import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../resources/js/Components/Add/AddChooser.vue', import.meta.url), 'utf8');

test('renders compact descriptions for every add choice', () => {
    assert.equal(source.match(/\{\{ choice\.description \}\}/g)?.length, 2);
    assert.equal(source.match(/variant="outline"/g)?.length, 2);
    assert.doesNotMatch(source, /min-h-28|bg-secondary\/70/);
});
