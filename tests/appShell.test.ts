import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

test('uses dark text for active bottom navigation links in dark mode', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.equal(source.match(/bg-primary dark:text-primary-foreground/g)?.length, 4);
});
