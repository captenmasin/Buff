import assert from 'node:assert/strict';
import test from 'node:test';
import { feetAndInchesFromInches, inchesFromFeetAndInches, measurementFromCm, measurementToCm } from '../resources/js/bodyUnits.ts';

test('converts between total inches and separate feet and inches fields', () => {
    assert.deepEqual(feetAndInchesFromInches(70.1), { feet: 5, inches: 10.1 });
    assert.equal(inchesFromFeetAndInches(5, 10.1), 70.1);
    assert.deepEqual(feetAndInchesFromInches(''), { feet: '', inches: '' });
    assert.equal(inchesFromFeetAndInches('', ''), '');
});

test('converts circumference measurements between centimeters and inches', () => {
    assert.equal(measurementFromCm(100, 'in'), 39.4);
    assert.equal(measurementToCm(39.4, 'in'), 100.1);
    assert.equal(measurementToCm('', 'in'), '');
});
