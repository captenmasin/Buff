import assert from 'node:assert/strict';
import test from 'node:test';
import { gramsForSplit, hasValidFivePercentSplit, macroCalories, macroPresets, normalizeSplit, splitFromGrams, splitWithinGramBounds } from '../resources/js/goalMacros.ts';

test('approved presets allocate 100% and match calorie targets', () => {
    for (const preset of macroPresets) {
        const grams = gramsForSplit(2000, preset);
        assert.equal(preset.protein + preset.carbs + preset.fat, 100);
        assert.equal(macroCalories(grams), 2000);
        assert.ok(Object.values(grams).every((value) => Number.isInteger(value * 100)));
    }
});

test('normalizes custom splits into valid five-percent allocations', () => {
    assert.deepEqual(normalizeSplit({ protein: -10, carbs: 200 }), { protein: 0, carbs: 100, fat: 0 });
    assert.deepEqual(normalizeSplit({ protein: 68, carbs: 68 }), { protein: 70, carbs: 30, fat: 0 });
    assert.deepEqual(splitFromGrams(2000, { protein: 174.91, carbs: 224.89, fat: 44.42 }), { protein: 35, carbs: 45, fat: 20 });
});

test('supports zero and boundary allocations', () => {
    assert.deepEqual(gramsForSplit(2000, { protein: 100, carbs: 0, fat: 0 }), { protein: 500, carbs: 0, fat: 0 });
    assert.deepEqual(gramsForSplit(2000, { protein: 0, carbs: 0, fat: 100 }), { protein: 0, carbs: 0, fat: 222.22 });
});

test('matches odd calorie targets with two-decimal grams', () => {
    const grams = gramsForSplit(1999, { protein: 35, carbs: 45, fat: 20 });
    assert.deepEqual(grams, { protein: 174.91, carbs: 224.89, fat: 44.42 });
    assert.equal(macroCalories(grams), 1999);
});

test('guards server gram ceilings and representable calorie targets', () => {
    assert.equal(splitWithinGramBounds(4000, { protein: 100, carbs: 0, fat: 0 }), true);
    assert.equal(splitWithinGramBounds(4001, { protein: 100, carbs: 0, fat: 0 }), false);
    assert.equal(splitWithinGramBounds(10000, macroPresets[0]), true);
    assert.equal(splitWithinGramBounds(10001, macroPresets[0]), false);
    assert.equal(splitWithinGramBounds(10000, macroPresets[1]), true);
    assert.equal(splitWithinGramBounds(10001, macroPresets[1]), false);
    assert.equal(splitWithinGramBounds(13333, macroPresets[2]), true);
    assert.equal(splitWithinGramBounds(13334, macroPresets[2]), false);
    assert.equal(hasValidFivePercentSplit(16000), true);
    assert.equal(hasValidFivePercentSplit(20000), false);
});
