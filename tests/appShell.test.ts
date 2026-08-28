import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

test('uses dark text for active bottom navigation links in dark mode', () => {
    const source = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');

    assert.equal(source.match(/bg-primary dark:text-primary-foreground/g)?.length, 4);
    assert.equal(source.match(/isActive\(navItems\[\d\]\.match\) \? 'default' : 'ghost'/g)?.length, 4);
});

test('keeps the settings back control separate from the page title', () => {
    const source = readFileSync(new URL('../resources/js/Components/SettingsPageHeader.vue', import.meta.url), 'utf8');

    assert.match(source, /items-center gap-1/);
    assert.doesNotMatch(source, /absolute left-0/);
});

test('shows the health provider name only in the settings page header', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Settings/Health.vue', import.meta.url), 'utf8');

    assert.match(source, /<SettingsPageHeader>{{ healthImport\?\.name \?\? 'Health' }}<\/SettingsPageHeader>/);
    assert.doesNotMatch(source, /<h2[^>]*>{{ healthImport\?\.name }}<\/h2>/);
});
