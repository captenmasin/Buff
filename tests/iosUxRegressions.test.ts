import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const appSource = readFileSync(new URL('../resources/js/app.ts', import.meta.url), 'utf8');
const packageJson = JSON.parse(readFileSync(new URL('../package.json', import.meta.url), 'utf8'));
const dailyTargetsEditorSource = readFileSync(new URL('../resources/js/Components/DailyTargetsEditor.vue', import.meta.url), 'utf8');
const mealTypePickerSource = readFileSync(new URL('../resources/js/Components/Add/MealTypePicker.vue', import.meta.url), 'utf8');
const recipeModeSource = readFileSync(new URL('../resources/js/Components/RecipeMode.vue', import.meta.url), 'utf8');
const todaySource = readFileSync(new URL('../resources/js/Pages/Today.vue', import.meta.url), 'utf8');
const viteConfigSource = readFileSync(new URL('../vite.config.ts', import.meta.url), 'utf8');

test('builds and launches iOS through the NativePHP asset and request pipeline', () => {
    assert.equal(packageJson.scripts['build:ios'], 'vite build --mode=ios');
    assert.equal(packageJson.scripts['native:ios'], 'pnpm run build:ios && php artisan native:run ios');
    assert.match(viteConfigSource, /hotFile: nativephpHotFile\(\)/);
    assert.match(viteConfigSource, /nativephpMobile\(\)/);
    assert.match(appSource, /http\.setClient\(axiosAdapter\(axios\)\)/);
});

test('defaults recipe logs to breakfast when the optional meal query is empty', () => {
    assert.equal(recipeModeSource.match(/meal_type: props\.meal \|\| 'breakfast'/g)?.length, 1);
    assert.equal(recipeModeSource.match(/logForm\.meal_type = props\.meal \|\| 'breakfast'/g)?.length, 1);
    assert.match(recipeModeSource, /logForm\.errors\.meal_type/);
    assert.match(recipeModeSource, /logForm\.errors\.servings/);
});

test('renders meal choices without a nested card', () => {
    assert.doesNotMatch(mealTypePickerSource, /rounded-xl border border-border bg-muted p-3/);
    assert.match(mealTypePickerSource, /modelValue === mealType \? 'default' : 'surface'/);
});

test('renders macro presets as proportional comparison rows', () => {
    assert.match(dailyTargetsEditorSource, /class="space-y-2" role="radiogroup" aria-label="Macro split"/);
    assert.match(dailyTargetsEditorSource, /text-protein">Protein \{\{ preset\.protein \}\}%/);
    assert.match(dailyTargetsEditorSource, /preset\.protein\}% protein/);
    assert.match(dailyTargetsEditorSource, /width: `\$\{preset\.protein\}%`/);
    assert.match(dailyTargetsEditorSource, /width: `\$\{preset\.carbs\}%`/);
    assert.match(dailyTargetsEditorSource, /width: `\$\{preset\.fat\}%`/);
    assert.doesNotMatch(dailyTargetsEditorSource, /grid grid-cols-2 overflow-hidden/);
});

test('closes the workout editor from the successful Inertia callback', () => {
    const closeWorkoutEditor = todaySource.match(/function closeWorkoutEditor\(\) \{[\s\S]*?\n\}/)?.[0] ?? '';

    assert.match(todaySource, /onSuccess: closeWorkoutEditor/);
    assert.doesNotMatch(closeWorkoutEditor, /editWorkoutForm\.processing/);
    assert.match(closeWorkoutEditor, /selectedWorkout\.value = null/);
});
