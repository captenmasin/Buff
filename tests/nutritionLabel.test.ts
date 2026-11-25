import assert from 'node:assert/strict';
import test from 'node:test';
import { scaleLabelNutrition } from '../resources/js/nutritionLabel.ts';

test('scales the printed calories and macros to the amount eaten', () => {
    const label = {calories: 240, protein_g: 10, carbs_g: 42, fat_g: 3.5};

    assert.deepEqual(scaleLabelNutrition(label, 30.5, 61), {
        calories: 120,
        protein_g: 5,
        carbs_g: 21,
        fat_g: 1.75,
    });
});
