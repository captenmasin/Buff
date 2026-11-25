import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { calorieBudget } from '../resources/js/calorieRing.ts';

test('shows calories left and progress against the effective daily target', () => {
    const budget = calorieBudget(1306, 894, 2200);

    assert.equal(budget.amount, 894);
    assert.equal(budget.caption, 'kcal remaining');
    assert.equal(budget.percentLabel, '59%');
    assert.ok(Math.abs(budget.progress - 59.36) < 0.01);
    assert.equal(budget.overTarget, false);
});

test('fills the ring when intake exceeds the target while showing the actual percentage', () => {
    const budget = calorieBudget(2600, -300, 2300);

    assert.equal(budget.amount, 300);
    assert.equal(budget.caption, 'kcal over target');
    assert.equal(budget.progress, 100);
    assert.equal(budget.percentLabel, '113%');
    assert.equal(budget.overTarget, true);
});

test('distinguishes an empty log from a target reached exactly', () => {
    assert.equal(calorieBudget(0, 2300, 2300).progress, 0);

    const budget = calorieBudget(2300, 0, 2300);
    assert.equal(budget.amount, 0);
    assert.equal(budget.caption, 'kcal remaining');
    assert.equal(budget.progress, 100);
    assert.equal(budget.overTarget, false);
});

test('shows calories eaten when there is no daily target', () => {
    const budget = calorieBudget(500, 0, 0);

    assert.equal(budget.hasTarget, false);
    assert.equal(budget.overTarget, false);
    assert.equal(budget.amount, 500);
    assert.equal(budget.caption, 'kcal eaten');
});

test('gives the calorie ring a named progress indicator with the visible percentage', () => {
    const source = readFileSync(new URL('../resources/js/Components/CalorieRing.vue', import.meta.url), 'utf8');

    assert.match(source, /<template v-if="budget\.hasTarget">\s*<progress[\s\S]*?aria-label="Daily calorie target"/);
    assert.match(source, /:aria-valuetext="[^"]*budget\.percentLabel/);
    assert.match(source, /aria-hidden="true">\s*<svg[\s\S]*?\{\{ budget\.percentLabel \}\}/);
    assert.match(source, /budget\.overTarget \? 'stroke-fat' : 'stroke-brand-acid'/);
});
