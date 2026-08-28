import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

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
