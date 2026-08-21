import assert from 'node:assert/strict';
import test from 'node:test';
import { dayStatusClass, dayStatusHasTick, dayStatusLabel } from '../resources/js/dayStatus.ts';

test('logged days show a tick and empty days do not', () => {
    assert.equal(dayStatusHasTick('target'), true);
    assert.equal(dayStatusHasTick('under'), true);
    assert.equal(dayStatusHasTick('over'), true);
    assert.equal(dayStatusHasTick('neutral'), false);
});

test('colours logged days by closeness to the daily goal', () => {
    assert.equal(dayStatusClass('target'), 'bg-success text-white');
    assert.equal(dayStatusClass('under'), 'bg-warning text-white');
    assert.equal(dayStatusClass('over'), 'bg-fat text-white');
    assert.equal(dayStatusClass('neutral'), 'border border-muted-foreground/50 bg-transparent');
});

test('names each status in words', () => {
    assert.equal(dayStatusLabel('target'), 'on target');
    assert.equal(dayStatusLabel('under'), 'under target');
    assert.equal(dayStatusLabel('over'), 'over target');
    assert.equal(dayStatusLabel('neutral'), 'no log');
});
