import assert from 'node:assert/strict';
import test from 'node:test';
import { accountReplacementDecision } from '../resources/js/accountReplacement.ts';

for (const provider of ['google', 'apple'] as const) {
    test(`${provider} launches when the device has no local account`, () => {
        assert.deepEqual(accountReplacementDecision(false, provider), { type: 'launch', provider });
    });

    test(`${provider} asks before replacing local account data`, () => {
        assert.deepEqual(accountReplacementDecision(true, provider), { type: 'confirm', provider });
    });
}
