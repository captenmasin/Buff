import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {compile} from 'tailwindcss';
import {nativephpHotFile} from '../vendor/nativephp/mobile/resources/js/vite-plugin.js';

const appSource = readFileSync(new URL('../resources/js/app.ts', import.meta.url), 'utf8');
const appStyles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');
const packageJson = JSON.parse(readFileSync(new URL('../package.json', import.meta.url), 'utf8'));
const bodyProfileEditorSource = readFileSync(new URL('../resources/js/Components/BodyProfileEditor.vue', import.meta.url), 'utf8');
const dailyTargetsEditorSource = readFileSync(new URL('../resources/js/Components/DailyTargetsEditor.vue', import.meta.url), 'utf8');
const goalsSource = readFileSync(new URL('../resources/js/Pages/Goals.vue', import.meta.url), 'utf8');
const mealTypePickerSource = readFileSync(new URL('../resources/js/Components/Add/MealTypePicker.vue', import.meta.url), 'utf8');
const onboardingSource = readFileSync(new URL('../resources/js/Pages/Onboarding.vue', import.meta.url), 'utf8');
const progressSource = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');
const calendarSource = readFileSync(new URL('../resources/js/Components/ui/calendar/Calendar.vue', import.meta.url), 'utf8');
const recipeModeSource = readFileSync(new URL('../resources/js/Components/RecipeMode.vue', import.meta.url), 'utf8');
const todaySource = readFileSync(new URL('../resources/js/Pages/Today.vue', import.meta.url), 'utf8');
const viteConfigSource = readFileSync(new URL('../vite.config.ts', import.meta.url), 'utf8');
const inputSource = readFileSync(new URL('../resources/js/Components/ui/input/Input.vue', import.meta.url), 'utf8');

test('builds and launches iOS through the NativePHP asset and request pipeline', () => {
    assert.equal(packageJson.scripts['build:ios'], 'vite build --mode=ios');
    assert.equal(packageJson.scripts['native:ios'], 'pnpm run build:ios && php artisan native:run ios');
    assert.match(viteConfigSource, /hotFile: nativephpHotFile\(\)/);
    assert.match(viteConfigSource, /nativephpMobile\(\)/);
    assert.match(appSource, /http\.setClient\(axiosAdapter\(axios\)\)/);
});

test('native build scripts use platform flags recognized by NativePHP', () => {
    const originalArgs = process.argv;

    try {
        for (const platform of ['ios', 'android']) {
            process.argv = [...originalArgs.slice(0, 2), ...packageJson.scripts[`build:${platform}`].split(' ').slice(1)];

            assert.equal(nativephpHotFile(), `public/${platform}-hot`);
        }
    } finally {
        process.argv = originalArgs;
    }
});

test('insets native date and time picker indicators', () => {
    assert.match(appStyles, /\[data-slot='input'\]::-webkit-calendar-picker-indicator \{\s+margin-right: 0\.375rem;/);
});

test('disables native theme sizing only for shared time inputs', async () => {
    const inputClasses = inputSource.match(/'([^'\n]+h-12[^'\n]+)'/)?.[1].split(/\s+/) ?? [];
    const compiler = await compile('@tailwind utilities;');
    const styles = compiler.build(inputClasses.filter((value) => value.includes('appearance-')));

    assert.match(styles, /\[type=time\]\s*\{\s*appearance: none;/);
    assert.doesNotMatch(styles, /\.appearance-none\s*\{/);
    assert.match(inputSource, /h-12 w-full min-w-0/);
});

test('retains native time inputs and caller sizing for workouts and reminders', () => {
    const paths = ['Add.vue', 'Today.vue', 'Settings/Reminders.vue'];

    for (const path of paths) {
        const source = readFileSync(new URL(`../resources/js/Pages/${path}`, import.meta.url), 'utf8');
        const field = source.match(/<Input\b[^>]*type="time"[^>]*>/)?.[0] ?? '';

        assert.match(field, /v-model=/, path);
        assert.doesNotMatch(field, /appearance-auto|@(?:click|focus)\.prevent/, path);

        if (path === 'Settings/Reminders.vue') {
            assert.match(field, /w-\[7\.5rem\] shrink-0/);
            assert.match(field, /@change="saveMealReminders"/);
        }
    }
});

test('defaults recipe logs from the current time when the optional meal query is empty', () => {
    assert.equal(recipeModeSource.match(/props\.meal \|\| smartMealType\(\)/g)?.length, 2);
    assert.match(recipeModeSource, /if \(hour < 10\) return 'breakfast';/);
    assert.match(recipeModeSource, /if \(hour < 14\) return 'lunch';/);
    assert.match(recipeModeSource, /if \(hour < 20\) return 'dinner';/);
    assert.match(recipeModeSource, /logForm\.errors\.meal_type/);
    assert.match(recipeModeSource, /logForm\.errors\.servings/);
});

test('renders meal choices as a distinct selection control', () => {
    assert.doesNotMatch(mealTypePickerSource, /rounded-xl border border-border bg-muted p-3/);
    assert.match(mealTypePickerSource, /role="radiogroup" aria-label="Meal type"/);
    assert.match(mealTypePickerSource, /variant="surface"/);
    assert.match(mealTypePickerSource, /modelValue === mealType \? 'border-brand-violet bg-brand-violet\/10 ring-1 ring-brand-violet' : ''/);
    assert.match(mealTypePickerSource, /:aria-checked="modelValue === mealType"/);
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

test('keeps custom macro wheels stable during programmatic positioning', () => {
    assert.doesNotMatch(dailyTargetsEditorSource, /scrollTo\(\{[^}]*behavior: 'smooth'/);
    assert.equal(dailyTargetsEditorSource.match(/role="listbox"/g)?.length, 1);
    assert.match(dailyTargetsEditorSource, /role="option"/);
    assert.match(dailyTargetsEditorSource, /:aria-selected="customSplit\[column\.key\] === percent"/);
    assert.match(dailyTargetsEditorSource, /:aria-label="`\$\{column\.label\} \$\{percent\} percent`"/);
});

test('keeps onboarding measurements canonical and validates displayed weight bounds', () => {
    assert.match(onboardingSource, /let syncingDisplayUnits = false/);
    assert.match(onboardingSource, /Number\(form\.age\) <= 18/);
    assert.match(onboardingSource, /weight >= 20 && weight <= 1000/);
    assert.match(onboardingSource, /:min="currentWeightMinimum" :max="weightMaximum"/);
    assert.match(onboardingSource, /:min="targetWeightMinimum" :max="weightMaximum"/);
});

test('uses cloud weight limits in the selected unit for onboarding and progress', () => {
    assert.match(onboardingSource, /currentWeightMinimum = computed\(\(\) => weightFromKg\(20, form\.weight_unit\)/);
    assert.equal(onboardingSource.match(/weight >= 20 && weight <= 1000/g)?.length, 2);
    assert.match(progressSource, /weightMinimum = computed\(\(\) => weightFromKg\(20, props\.preferences\.weight_unit\)/);
    assert.match(progressSource, /weightMaximum = computed\(\(\) => weightFromKg\(1000, props\.preferences\.weight_unit\)/);
    assert.match(progressSource, /v-model="metricForm\.weight_kg"[^>]*:min="weightMinimum" :max="weightMaximum"/);
});

test('mirrors server profile and goal bounds while allowing optional selectors to clear', () => {
    assert.equal(bodyProfileEditorSource.match(/<SelectItem :value="unsetSelection">Not set<\/SelectItem>/g)?.length, 2);
    assert.match(bodyProfileEditorSource, /type="number" min="50" max="260"/);
    assert.match(goalsSource, /weightFromKg\(20, props\.preferences\.weight_unit\)/);
    assert.match(goalsSource, /:disabled="!targetsValid \|\| !targetWeightIsValid \|\| !form\.isDirty"/);
});

test('closes the workout editor from the successful Inertia callback', () => {
    const closeWorkoutEditor = todaySource.match(/function closeWorkoutEditor\(\) \{[\s\S]*?\n\}/)?.[0] ?? '';

    assert.match(todaySource, /onSuccess: closeWorkoutEditor/);
    assert.doesNotMatch(closeWorkoutEditor, /editWorkoutForm\.processing/);
    assert.match(closeWorkoutEditor, /selectedWorkout\.value = null/);
});

test('does not render the meal editor after its selected meal is cleared', () => {
    assert.match(todaySource, /v-if="mealSheetMode === 'details' && selectedMeal"/);
    assert.match(todaySource, /v-else-if="mealSheetMode === 'edit' && selectedMeal"/);
    assert.doesNotMatch(todaySource, /<div v-else key="edit">/);
});

test('makes meal day, repeat, edit errors, and recipe portions explicit', () => {
    assert.match(todaySource, /:aria-current="day\.is_selected \? 'date' : undefined"/);
    assert.match(todaySource, />Repeat<\/Button>/);
    assert.match(todaySource, /editMealForm\.errors\.name/);
    assert.match(todaySource, /editMealForm\.errors\.meal_type/);
    assert.match(todaySource, /serving\$\{Number\(entry\.portion_quantity\) === 1 \? '' : 's'\}/);
});

test('initializes calendars from their selected value and closes a reselected day', () => {
    assert.match(calendarSource, /Array\.isArray\(props\.modelValue\) \? props\.modelValue\[0\] : props\.modelValue/);
    assert.match(calendarSource, /emits\('dayClick', weekDate\)/);
    assert.match(todaySource, /@day-click="\(value\) => closeSelectedDate\(value, close\)"/);
});

test('keeps date errors and health actions visible on an empty day', () => {
    assert.match(todaySource, /v-if="dateError"[^>]*role="alert"/);
    assert.match(todaySource, /<section v-if="showDayLists \|\| showHealthConnect"/);
});

test('clears corrected workout edit errors immediately', () => {
    for (const field of ['title', 'calories_burned', 'time']) {
        assert.match(todaySource, new RegExp(`editWorkoutForm\\.clearErrors\\('${field}'\\)`));
    }
});
