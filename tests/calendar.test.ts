import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const calendarSource = readFileSync(new URL('../resources/js/Components/ui/calendar/Calendar.vue', import.meta.url), 'utf8');

test('pads the month and year selector labels', () => {
    assert.equal(calendarSource.match(/items-center text-sm pl-5 pointer-events-none/g)?.length, 2);
});
