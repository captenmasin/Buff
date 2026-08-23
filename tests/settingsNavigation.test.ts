import assert from 'node:assert/strict';
import test from 'node:test';
import {settingsNavDirection, settingsPathname, settingsStackDepth} from '../resources/js/settingsNavigation.ts';

test('treats the settings hub as the stack root', () => {
    assert.equal(settingsPathname('/settings/appearance?x=1'), '/settings/appearance');
    assert.equal(settingsStackDepth('/settings'), 1);
    assert.equal(settingsStackDepth('/settings/units'), 2);
    assert.equal(settingsStackDepth('/goals'), 0);
});

test('slides forward into a settings section and back to the hub', () => {
    assert.equal(settingsNavDirection('/settings', '/settings/appearance'), 'forward');
    assert.equal(settingsNavDirection('/settings/appearance', '/settings'), 'back');
    assert.equal(settingsNavDirection('/settings/units', '/settings/units'), null);
    assert.equal(settingsNavDirection('/settings', '/goals'), null);
    assert.equal(settingsNavDirection('/', '/settings'), null);
});
