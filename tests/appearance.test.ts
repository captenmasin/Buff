import assert from 'node:assert/strict';
import test from 'node:test';
import {shouldReduceMotion} from '../resources/js/appearance.ts';

test('reduces motion when requested by the user or their device', () => {
    assert.equal(shouldReduceMotion(false, false), false);
    assert.equal(shouldReduceMotion(true, false), true);
    assert.equal(shouldReduceMotion(false, true), true);
});
