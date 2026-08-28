import assert from 'node:assert/strict';
import test from 'node:test';
import { dayStatusClass, dayStatusHasTick, dayStatusIcon, dayStatusLabel } from '../resources/js/dayStatus.ts';

test('only on-target days show a tick', () => {
    assert.equal(dayStatusHasTick('target'), true);
    assert.equal(dayStatusHasTick('under'), false);
    assert.equal(dayStatusHasTick('over'), false);
    assert.equal(dayStatusHasTick('neutral'), false);
});

test('colours logged days by closeness to the daily goal', () => {
    assert.equal(dayStatusClass('target'), 'bg-success text-white');
    assert.equal(dayStatusClass('under'), 'bg-warning-soft text-warning-soft-foreground');
    assert.equal(dayStatusClass('over'), 'bg-danger-soft text-danger-soft-foreground');
    assert.equal(dayStatusClass('neutral'), 'border border-muted-foreground/50 bg-transparent');
});

test('names each status in words', () => {
    assert.equal(dayStatusLabel('target'), 'on target');
    assert.equal(dayStatusLabel('under'), 'under target');
    assert.equal(dayStatusLabel('over'), 'over target');
    assert.equal(dayStatusLabel('neutral'), 'no log');
});

test('uses minus and plus glyphs when a day is under or over', () => {
    assert.equal(dayStatusIcon('target'), 'check');
    assert.equal(dayStatusIcon('under'), 'minus');
    assert.equal(dayStatusIcon('over'), 'plus');
    assert.equal(dayStatusIcon('neutral'), null);
});
