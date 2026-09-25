import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { calorieRemainingAriaLabel, calorieRemainingLabel, calorieRingStrokeClass } from '../resources/js/calorieRing.ts';

test('keeps the calorie ring green at or under the daily target', () => {
    assert.equal(calorieRingStrokeClass(0, 2410), 'stroke-success');
    assert.equal(calorieRingStrokeClass(2410, 2410), 'stroke-success');
    assert.equal(calorieRingStrokeClass(1800, 2410), 'stroke-success');
});

test('turns the calorie ring red when intake exceeds the daily target', () => {
    assert.equal(calorieRingStrokeClass(2411, 2410), 'stroke-destructive');
    assert.equal(calorieRingStrokeClass(2994, 2410), 'stroke-destructive');
});

test('stays green when there is no daily target yet', () => {
    assert.equal(calorieRingStrokeClass(500, 0), 'stroke-success');
});

test('says how many calories are left under the daily target', () => {
    assert.equal(calorieRemainingLabel(300, 2300), '300 left of 2300');
    assert.equal(calorieRemainingLabel(0, 2300), '0 left of 2300');
});

test('says how far over budget calories are instead of a negative left amount', () => {
    assert.equal(calorieRemainingLabel(-300, 2300), "You're 300 over");
    assert.equal(calorieRemainingLabel(-1.4, 2300), "You're 1 over");
});

test('falls back when there is no daily calorie target', () => {
    assert.equal(calorieRemainingLabel(100, 0), 'No daily target yet');
});

test('keeps screen-reader copy aligned with the visible remaining label', () => {
    assert.equal(calorieRemainingAriaLabel(2000, 300, 2300), '2000 of 2300 calories, 300 remaining');
    assert.equal(calorieRemainingAriaLabel(2600, -300, 2300), '2600 of 2300 calories, 300 over');
    assert.equal(calorieRemainingAriaLabel(500, 0, 0), '500 calories, no daily target yet');
});

test('binds the stroke class from the calorie ring helper', () => {
    const source = readFileSync(new URL('../resources/js/Components/CalorieRing.vue', import.meta.url), 'utf8');

    assert.match(source, /import \{ calorieRemainingAriaLabel, calorieRemainingLabel, calorieRingStrokeClass \} from '\.\.\/calorieRing'/);
    assert.match(source, /calorieRingStrokeClass\(props\.consumed, props\.goal\)/);
    assert.match(source, /calorieRemainingLabel\(props\.remaining, props\.goal\)/);
    assert.match(source, /:class="strokeClass"/);
    assert.match(source, /\{\{ remainingLabel \}\}/);
    assert.doesNotMatch(source, /\{\{ remaining \}\} left of \{\{ goal \}\}/);
});
