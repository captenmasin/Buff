import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import { nextTick, reactive, vModelSelect } from 'vue';
import { useVModel } from '@vueuse/core';

const calendarSource = readFileSync(new URL('../resources/js/Components/ui/calendar/Calendar.vue', import.meta.url), 'utf8');
const calendarCellSource = readFileSync(new URL('../resources/js/Components/ui/calendar/CalendarCell.vue', import.meta.url), 'utf8');
const nativeSelectSource = readFileSync(new URL('../resources/js/Components/ui/native-select/NativeSelect.vue', import.meta.url), 'utf8');

function calendarSelect(unit: 'month' | 'year', date: { month: number; year: number }) {
    const template = unit === 'month' ? 'Month' : 'Year';
    const block = calendarSource.match(new RegExp(`<Define${template}Template[^]*?</Define${template}Template>`))?.[0] ?? '';
    const binding = block.match(/:model-value="([^"]+)"/)?.[1];
    const boundValue = (value: typeof date) => binding ? new Function('date', `return (${binding});`)(value) : undefined;
    const props = reactive({ modelValue: boundValue(date) });
    const declaration = nativeSelectSource.match(/const modelValue = useVModel[^]*?\n\}\)/)?.[0];
    assert.ok(declaration);
    const model = new Function('props', 'emit', 'useVModel', `${declaration}; return modelValue;`)(props, () => {}, useVModel);
    const values = unit === 'month' ? Array.from({ length: 12 }, (_, index) => index + 1) : Array.from({ length: 111 }, (_, index) => 1926 + index);
    const select = { multiple: false, selectedIndex: values.indexOf(date[unit]), options: values.map((value) => ({ value: String(value), _value: value })) };
    const directive = vModelSelect as {
        mounted: (element: unknown, binding: { value: unknown }) => void;
        updated: (element: unknown, binding: { value: unknown }) => void;
    };

    return { props, model, select, boundValue, directive };
}

test('initializes native month and year selection from the displayed calendar date', () => {
    for (const unit of ['month', 'year'] as const) {
        const state = calendarSelect(unit, { month: 9, year: 2026 });

        state.directive.mounted(state.select, { value: state.model.value });

        assert.equal(state.select.selectedIndex, unit === 'month' ? 8 : 100);
        assert.equal(state.select.options[state.select.selectedIndex]._value, unit === 'month' ? 9 : 2026);
    }
});

test('keeps native month and year selection aligned after calendar navigation', async () => {
    for (const unit of ['month', 'year'] as const) {
        const state = calendarSelect(unit, { month: 9, year: 2026 });
        state.directive.mounted(state.select, { value: state.model.value });

        state.props.modelValue = state.boundValue({ month: 10, year: 2027 });
        await nextTick();
        state.directive.updated(state.select, { value: state.model.value });

        assert.equal(state.select.selectedIndex, unit === 'month' ? 9 : 101);
        assert.equal(state.select.options[state.select.selectedIndex]._value, unit === 'month' ? 10 : 2027);
    }
});

test('pads the month and year selector labels', () => {
    assert.equal(calendarSource.match(/items-center text-sm pl-5 pointer-events-none/g)?.length, 2);
});

test('does not paint outside the selected day', () => {
    assert.doesNotMatch(calendarCellSource, /has\(\[data-selected\]\).*bg-/);
});
