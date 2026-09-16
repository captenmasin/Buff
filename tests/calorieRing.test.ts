import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { calorieRingStrokeClass } from '../resources/js/calorieRing.ts';

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

test('binds the stroke class from the calorie ring helper', () => {
    const source = readFileSync(new URL('../resources/js/Components/CalorieRing.vue', import.meta.url), 'utf8');

    assert.match(source, /import \{ calorieRingStrokeClass \} from '\.\.\/calorieRing'/);
    assert.match(source, /calorieRingStrokeClass\(props\.consumed, props\.goal\)/);
    assert.match(source, /:class="strokeClass"/);
});
