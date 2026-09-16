import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import test from 'node:test';
import { router, useForm } from '@inertiajs/vue3';
import { withModifiers } from 'vue';

const recipeSource = readFileSync(new URL('../resources/js/Components/RecipeMode.vue', import.meta.url), 'utf8');
const addSource = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');
const themeSource = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');

test('uses the violet brand palette for food accents', () => {
    assert.match(themeSource, /--food: var\(--brand-violet\);/);
    assert.match(themeSource, /--food: #a99fff;/i);
    assert.doesNotMatch(themeSource, /--food: #(9a5720|e0a06a);/i);
});

test('shows a single clear action in the empty recipe state', () => {
    assert.match(recipeSource, /Save meals you repeat/);
    assert.match(recipeSource, /Create your first recipe/);
    assert.doesNotMatch(recipeSource, /No saved recipes yet\./);
});

test('uses singular ingredient copy only for a one-item saved recipe', () => {
    const label = recipeSource.match(/<span[^>]*>(\{\{ recipe.calories \}\} kcal · \{\{ recipe.items.length \}\}[^<]*)<\/span>/)?.[1];
    assert.ok(label);

    for (const [count, expected] of [[0, '165 kcal · 0 ingredients'], [1, '165 kcal · 1 ingredient'], [2, '165 kcal · 2 ingredients']] as const) {
        const recipe = { calories: 165, items: Array(count).fill({}) };

        const text = label.replace(/\{\{(.*?)\}\}/g, (_, expression) => String(new Function('recipe', `return (${expression});`)(recipe)));

        assert.equal(text, expected);
    }
});

test('switches between all foods and recipes under one add food page', () => {
    assert.match(addSource, /aria-label="Food source"/);
    assert.match(addSource, /addModeUrl\('food'\)/);
    assert.match(addSource, /addModeUrl\('recipe'\)/);
    assert.match(addSource, /All foods/);
    assert.match(addSource, /mode === 'food' \|\| mode === 'recipe' \? 'Add food'/);
});

test('keeps food search primary and groups the alternative entry actions', () => {
    const foodSearchCard = addSource.match(/<Card v-if="mode === 'food'">[\s\S]*?<p v-if="nativeMessage"/)?.[0] ?? '';

    assert.match(foodSearchCard, /<form role="search" class="relative"/);
    assert.match(foodSearchCard, /aria-label="Search foods"/);
    assert.match(foodSearchCard, /grid grid-cols-2 gap-2 max-\[360px\]:grid-cols-1/);
    assert.match(foodSearchCard, /Scan barcode/);
    assert.match(foodSearchCard, /Add custom food/);
    assert.doesNotMatch(foodSearchCard, /<h2[^>]*>Search food<\/h2>/);
});

test('exposes product portions and recipe log controls accessibly', () => {
    assert.match(addSource, /aria-label="Portion quantity"/);
    assert.match(addSource, /aria-label="Portion unit"/);
    assert.match(addSource, /:aria-pressed="selectedPortionKey === String\(index\)"/);
    assert.match(addSource, /barcodeMealForm\.errors/);
    assert.match(recipeSource, /required class="mt-1"/);
    assert.match(recipeSource, /logForm\.clearErrors\(\)/);
    assert.match(recipeSource, /:aria-label="`Delete \$\{recipe\.name\}`"/);
});

test('shows custom macro errors and clears corrected workout creation errors', () => {
    assert.match(addSource, /customMealForm\.errors\[field\[0\]\]/);

    for (const field of ['title', 'calories_burned', 'time']) {
        assert.match(addSource, new RegExp(`workoutForm\\.clearErrors\\('${field}'\\)`));
    }
});

test('keeps native recipe serving guidance inline and clears it when corrected', () => {
    const input = recipeSource.match(/<Input\b[^>]*v-model\.number="logForm\.servings"[^>]*\/>/)?.[0];
    const invalid = input?.match(/@invalid\.([\w.]+)="([^"]+)"/);
    const corrected = input?.match(/@update:model-value="([^"]+)"/)?.[1];
    assert.ok(invalid, 'recipe servings invalid handler');
    assert.ok(corrected, 'recipe servings correction handler');
    const form = useForm({ servings: 0.15, meal_type: 'lunch' });
    form.setError('meal_type', 'Choose a meal type.');
    const message = 'Please enter a valid value. The two nearest valid values are 0.1 and 0.2.';
    const event = new Event('invalid', { cancelable: true });
    Object.defineProperty(event, 'target', { value: { validationMessage: message } });
    const handler = new Function('$event', 'logForm', stripTypeScriptTypes(invalid[2]));

    withModifiers((event: Event) => handler(event, form), invalid[1].split('.'))(event);

    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(form.errors, { meal_type: 'Choose a meal type.', servings: message });
    assert.equal(form.servings, 0.15);

    form.servings = 0.2;
    new Function('logForm', corrected)(form);

    assert.deepEqual(form.errors, { meal_type: 'Choose a meal type.' });
    assert.equal(form.servings, 0.2);
});

test('clears corrected custom food fields independently', () => {
    const template = addSource.match(/<form[^>]*@submit.prevent="addCustomMeal"[^>]*>[\s\S]*?<\/form>/)?.[0];
    assert.ok(template);

    for (const field of ['name', 'portion_quantity', 'portion_unit'] as const) {
        const input = template.match(/<(?:Input|Select)\b[^>]*>/g)?.find((tag) => tag.includes(`="customMealForm.${field}"`));
        const handler = input?.match(/@update:model-value="([^"]+)"/)?.[1];
        assert.ok(handler, `${field} change handler`);
        const form = useForm({ name: 'Corrected meal', portion_quantity: 350, portion_unit: 'ml', protein_g: 45 });
        const errors = { name: 'Required', portion_quantity: 'Too large', portion_unit: 'Invalid unit', protein_g: 'Too large' };
        form.setError(errors);
        const values = form.data();

        new Function('customMealForm', handler)(form);

        assert.deepEqual(form.errors, Object.fromEntries(Object.entries(errors).filter(([key]) => key !== field)));
        assert.deepEqual(form.data(), values);
    }
});

test('preserves custom food and photo review scroll only for validation errors', async (context) => {
    const declaration = addSource.match(/function addCustomMeal\(\) \{[\s\S]*?\n\}/)?.[0];
    assert.ok(declaration);

    const visits: { url: string; data: Record<string, unknown>; preserveScroll: unknown }[] = [];
    context.mock.method(router, 'post', (url, data, options) => {
        visits.push({ url, data, preserveScroll: options.preserveScroll });
        options.onError({ name: 'The name field is required.' });
    });

    for (const analysisId of ['', '01a07176-6e67-7ab0-b155-7d367354c529']) {
        const form = useForm({ date: '2026-09-04', meal_type: 'lunch', name: '', portion_quantity: 100, portion_unit: 'g', protein_g: '', carbs_g: '60', fat_g: '20', analysis_id: analysisId });
        const submit = new Function('customMealForm', 'selectedMealType', 'hapticImpact', `${declaration}; return addCustomMeal;`)(form, { value: 'dinner' }, () => {});

        submit();
        await Promise.resolve();

        assert.deepEqual(visits.at(-1), {
            url: '/meals/custom',
            data: { date: '2026-09-04', meal_type: 'dinner', name: '', portion_quantity: 100, portion_unit: 'g', protein_g: 0, carbs_g: 60, fat_g: 20, analysis_id: analysisId },
            preserveScroll: 'errors',
        });
        assert.equal(form.errors.name, 'The name field is required.');
        assert.equal(form.analysis_id, analysisId);
        assert.equal(form.carbs_g, '60');
    }

    assert.equal(visits.length, 2);
});

test('resets abandoned recipe drafts before opening an editor', () => {
    const resetEditor = recipeSource.match(/function resetRecipeEditor\(\): void \{[\s\S]*?\n\}/)?.[0] ?? '';

    assert.match(resetEditor, /recipeForm\.resetAndClearErrors\(\)/);
    assert.match(resetEditor, /resetFoodSearch\(\)/);
    assert.match(resetEditor, /customItem\.value = newCustomItem\(\)/);
    assert.match(recipeSource, /window\.clearTimeout\(searchTimer\);\s+searchTimer = 0;\s+searchRequest\+\+;/);
    assert.match(recipeSource, /function startCreate\(\): void \{\s+resetRecipeEditor\(\);/);
    assert.match(recipeSource, /function startEdit\(recipe: Recipe\): void \{\s+resetRecipeEditor\(\);/);
    assert.match(recipeSource, /function returnToRecipes\(\): void \{[\s\S]*resetRecipeEditor\(\);/);
});

test('blocks invalid custom ingredients and exposes nested item errors', () => {
    assert.match(recipeSource, /Number\.isFinite\(portionQuantity\)/);
    assert.match(recipeSource, /macroValues\.some\(\(value\) => !Number\.isFinite\(value\) \|\| value < 0 \|\| value > 1000\)/);
    assert.match(recipeSource, /customItemError\.value = 'Amount must be between 0\.1 and 10,000 g or ml\.'/);
    assert.match(recipeSource, /function recipeItemErrors\(index: number\): string\[\]/);
    assert.match(recipeSource, /v-for="error in recipeItemErrors\(index\)"/);
    assert.match(recipeSource, /role="alert"/);
});

function customIngredientEditor(values: Record<string, unknown> = {}) {
    const declarations = ['customCalories', 'newCustomItem', 'addCustomItem'].map((name) => {
        const declaration = recipeSource.match(new RegExp(`function ${name}\\([^]*?\\n\\}`))?.[0];
        assert.ok(declaration, name);

        return declaration;
    }).join('\n');

    return new Function('values', `
        ${stripTypeScriptTypes(declarations)}
        const customItem = { value: { ...newCustomItem(), name: 'QA ingredient', ...values } };
        const recipeForm = { items: [] };
        const customItemError = { value: '' };
        return { customItem, recipeForm, customItemError, addCustomItem };
    `)(values);
}

test('rejects empty and malformed custom macros without resetting or appending the ingredient', () => {
    for (const field of ['protein_g', 'carbs_g', 'fat_g']) {
        for (const value of ['', ' ', '--', 'invalid', Number.NaN, Infinity, -Infinity, null, undefined]) {
            const editor = customIngredientEditor({ [field]: value });
            const draft = editor.customItem.value;

            editor.addCustomItem();

            assert.equal(editor.customItemError.value, 'Protein, carbs, and fat must each be between 0 and 1,000 g.');
            assert.deepEqual(editor.recipeForm.items, []);
            assert.equal(editor.customItem.value, draft);
            assert.equal(editor.customItem.value[field], value);
        }
    }
});

test('rejects empty malformed nonfinite and out-of-range custom quantities before append', () => {
    for (const quantity of ['', '--', Number.NaN, Infinity, null, 0, 0.01, 10000.1]) {
        const editor = customIngredientEditor({ portion_quantity: quantity });
        const draft = editor.customItem.value;

        editor.addCustomItem();

        assert.equal(editor.customItemError.value, 'Amount must be between 0.1 and 10,000 g or ml.');
        assert.deepEqual(editor.recipeForm.items, []);
        assert.equal(editor.customItem.value, draft);
    }
});

test('preserves valid zero decimal and boundary custom ingredient values', () => {
    for (const [quantity, protein, carbs, fat, calories] of [[0.1, 0, 0, 0, 0], [100, 1.25, 0.5, 2.25, 27], [10000, 1000, 1000, 1000, 17000]]) {
        const editor = customIngredientEditor({ name: '  Valid ingredient  ', portion_quantity: quantity, portion_unit: 'ml', protein_g: protein, carbs_g: carbs, fat_g: fat });

        editor.addCustomItem();

        assert.equal(editor.customItemError.value, '');
        assert.deepEqual(editor.recipeForm.items, [{ name: 'Valid ingredient', food_product_id: null, portion_quantity: quantity, portion_unit: 'ml', calories, protein_g: protein, carbs_g: carbs, fat_g: fat }]);
        assert.deepEqual(editor.customItem.value, { name: '', portion_quantity: 100, portion_unit: 'g', protein_g: 0, carbs_g: 0, fat_g: 0 });
    }
});

test('edits saved recipes with the existing recipe form and update endpoint', () => {
    assert.match(recipeSource, /const editingRecipe = ref<Recipe \| null>\(null\)/);
    assert.match(recipeSource, /recipeForm\.items = recipe\.items\.map\(\(item\) => \(\{\.\.\.item\}\)\)/);
    assert.match(recipeSource, /recipeForm\.put\(`\/recipes\/\$\{editingRecipe\.value\.id\}`/);
    assert.match(recipeSource, /:aria-label="`Edit \$\{recipe\.name\}`"/);
    assert.match(recipeSource, /editingRecipe \? 'Edit recipe' : 'New recipe'/);
});
