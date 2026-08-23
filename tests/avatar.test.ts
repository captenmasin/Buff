import assert from 'node:assert/strict';
import test from 'node:test';
import { avatarColorClass, avatarInitials } from '../resources/js/avatar.ts';

test('builds initials from the first and last names', () => {
    assert.equal(avatarInitials('Mason Reed'), 'MR');
    assert.equal(avatarInitials('Mason'), 'M');
    assert.equal(avatarInitials('  '), '?');
});

test('keeps avatar colours stable for the same normalized name', () => {
    assert.equal(avatarColorClass('Mason Reed'), avatarColorClass('  mason reed  '));
    assert.notEqual(avatarColorClass('Mason Reed'), avatarColorClass('Alex Chen'));
});
