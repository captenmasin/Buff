import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const recipeModeSource = readFileSync(new URL('../resources/js/Components/RecipeMode.vue', import.meta.url), 'utf8');
const todaySource = readFileSync(new URL('../resources/js/Pages/Today.vue', import.meta.url), 'utf8');

test('defaults recipe logs to breakfast when the optional meal query is empty', () => {
    assert.equal(recipeModeSource.match(/meal_type: props\.meal \|\| 'breakfast'/g)?.length, 1);
    assert.equal(recipeModeSource.match(/logForm\.meal_type = props\.meal \|\| 'breakfast'/g)?.length, 1);
    assert.match(recipeModeSource, /logForm\.errors\.meal_type/);
    assert.match(recipeModeSource, /logForm\.errors\.servings/);
});

test('closes the workout editor from the successful Inertia callback', () => {
    const closeWorkoutEditor = todaySource.match(/function closeWorkoutEditor\(\) \{[\s\S]*?\n\}/)?.[0] ?? '';

    assert.match(todaySource, /onSuccess: closeWorkoutEditor/);
    assert.doesNotMatch(closeWorkoutEditor, /editWorkoutForm\.processing/);
    assert.match(closeWorkoutEditor, /selectedWorkout\.value = null/);
});
