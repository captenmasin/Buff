import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

test('posts account deletion with Laravel method spoofing', () => {
    const settingsSource = readFileSync(new URL('../resources/js/Pages/Settings.vue', import.meta.url), 'utf8');

    assert.match(settingsSource, /transform\(\(data\) => \(\{\.\.\.data, _method: 'delete'\}\)\)\.post\('\/account'/);
    assert.match(settingsSource, /does not cancel an App Store or Google Play subscription/);
    assert.match(settingsSource, /href="\/settings\/subscription"/);
});
