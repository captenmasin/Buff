import assert from 'node:assert/strict';
import test from 'node:test';
import { isNavigatorOnline } from '../resources/js/networkStatus.ts';

test('treats an explicit offline navigator as offline', () => {
    assert.equal(isNavigatorOnline({ onLine: false }), false);
});

test('treats a connected navigator as online', () => {
    assert.equal(isNavigatorOnline({ onLine: true }), true);
});

test('assumes online when connectivity is unknown', () => {
    assert.equal(isNavigatorOnline({}), true);
    assert.equal(isNavigatorOnline(undefined), true);
});
