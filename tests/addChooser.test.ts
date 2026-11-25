import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../resources/js/Components/Add/AddChooser.vue', import.meta.url), 'utf8');
const shell = readFileSync(new URL('../resources/js/Layouts/AppShell.vue', import.meta.url), 'utf8');
const workout = readFileSync(new URL('../resources/js/Components/Add/WorkoutEntryForm.vue', import.meta.url), 'utf8');

test('starts on Food with capture shortcuts and manual entries, with Workout in its own tab', () => {
    assert.match(source, /<TabsRoot default-value="food" :unmount-on-hide="false">/);
    assert.deepEqual([...source.matchAll(/label: '([^']+)'/g)].map(([, label]) => label), [
        'Search', 'Scan', 'Photo', 'Custom', 'Recipe',
    ]);
    assert.match(source, /mode: 'food', extra: \{ scan: '1' \}, label: 'Scan'/);
    assert.match(source, /<TabsContent value="workout"[^>]*>[\s\S]*?<slot name="workout"\s*\/>/);
});

test('marks photo analysis as Buff+ when the cached server expiry is inactive', () => {
    assert.match(source, /choice.mode === 'photo' && !props.subscriptionActive[\s\S]*?<LockKeyhole[\s\S]*?Buff\+ required/);
});

test('the drawer uses the selected journal date and closes after a successful workout save', () => {
    assert.match(shell, /<template #workout>\s*<WorkoutEntryForm\s+:date="page.props.summary\?\.date"\s+:replace-history="drawerHistoryActive"\s+@saved="closeDrawerImmediately"/);
    assert.match(workout, /form\.post\('\/workouts', \{\s+replace: props\.replaceHistory \?\? false,\s+onSuccess: \(\) => emit\('saved'\)/);
});
