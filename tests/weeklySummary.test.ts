import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { stripTypeScriptTypes } from 'node:module';
import test from 'node:test';
import { computed, createSSRApp, reactive, ref, unref } from 'vue';
import { renderToString } from 'vue/server-renderer';

function weeklyControls(mode: 'week' | 'range', rejectedControls: Record<string, string | null>, errors: Record<string, string>) {
    const source = readFileSync(new URL('../resources/js/Pages/Weekly.vue', import.meta.url), 'utf8');
    const definitions = source.slice(source.indexOf('const hasRangeError ='), source.indexOf('const macroCards ='));
    const props = { mode, controls: { date: '2026-10-15', start_date: '2026-10-12', end_date: '2026-10-18' }, rejectedControls };
    const page = { props: { errors } };
    const controls = new Function('props', 'page', 'ref', `${stripTypeScriptTypes(definitions)}\nreturn { selectedMode, weekDate, startDate, endDate };`)(props, page, ref);

    return { source, page, ...controls };
}

for (const [name, startDate, endDate, field, message] of [
    ['reversed dates', '2026-10-19', '2026-10-18', 'end_date', 'The end date field must be a date after or equal to start date.'],
    ['more than 90 days', '2026-01-01', '2026-04-01', 'end_date', 'Choose a range of 90 days or less.'],
    ['a missing start date', null, '2026-10-18', 'start_date', 'The start date field is required when end date is present.'],
] as const) {
    test(`reopens Range with the attempted dates and visible error after ${name}`, async () => {
        const state = weeklyControls('week', { start_date: startDate, end_date: endDate }, { [field]: message });
        const template = state.source.match(/<form\b[\s\S]*?<\/form>/)?.[0];
        assert.ok(template);

        const html = await renderToString(createSSRApp({
            template,
            components: {
                Input: { props: ['modelValue'], template: '<input :value="modelValue">' },
                Button: { template: '<button><slot /></button>' },
                Popover: { template: '<div><slot :close="() => {}" /></div>' },
                PopoverTrigger: { template: '<div><slot /></div>' },
                PopoverContent: { template: '<div><slot /></div>' },
                Calendar: { template: '<div />' },
            },
            setup: () => ({ ...state, applySelection: () => {}, formatDisplayDate: (value: string) => value, parseDate: (value: string) => value }),
        }));

        assert.equal(unref(state.selectedMode), 'range');
        assert.ok(html.includes(`role="alert">${message}</span>`));
        assert.ok(html.includes(`value="${endDate}"`));
        assert.equal(unref(state.startDate), startDate ?? '');
        if (startDate !== null) assert.ok(html.includes(`value="${startDate}"`));
        assert.ok(html.includes('Apply'));
        assert.ok(!html.includes('Week containing'));
    });
}

test('keeps the successful selection mode when there is no range validation error', () => {
    for (const mode of ['week', 'range'] as const) {
        const state = weeklyControls(mode, { start_date: null, end_date: null }, {});

        assert.equal(unref(state.selectedMode), mode);
        assert.equal(unref(state.startDate), '2026-10-12');
        assert.equal(unref(state.endDate), '2026-10-18');
    }
});

test('refreshes weekly cards when resume sync replaces roundup props without remounting', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Weekly.vue', import.meta.url), 'utf8');
    const definitions = source.slice(source.indexOf('const macroCards ='), source.indexOf('function progress('));
    const props = reactive({
        roundup: {
            average_calories: 100 as number | null, calories: 100,
            average_target: 2000 as number | null, effective_target: 2000,
            protein_g: 10, protein_goal_g: 100,
            carbs_g: 20, carbs_goal_g: 200,
            fat_g: 5, fat_goal_g: 60,
        },
    });
    const cards = new Function('props', 'computed', `${definitions}\nreturn { macroCards, heroCalories, heroTarget };`)(props, computed);

    assert.equal(unref(cards.heroCalories), 100);
    assert.equal(unref(cards.macroCards)[0].consumed, 10);

    props.roundup = {
        average_calories: 300, calories: 600,
        average_target: 2100, effective_target: 14700,
        protein_g: 40, protein_goal_g: 140,
        carbs_g: 60, carbs_goal_g: 250,
        fat_g: 15, fat_goal_g: 70,
    };

    assert.equal(unref(cards.heroCalories), 300);
    assert.equal(unref(cards.heroTarget), 2100);
    assert.deepEqual(unref(cards.macroCards).map((card: { consumed: number; goal: number }) => [card.consumed, card.goal]), [[40, 140], [60, 250], [15, 70]]);

    props.roundup.average_calories = null;
    props.roundup.average_target = null;
    props.roundup.calories = 0;

    assert.equal(unref(cards.heroCalories), 0);
    assert.equal(unref(cards.heroTarget), 14700);
});

test('lets daily nutrition details wrap without moving the date and status out of their columns', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Weekly.vue', import.meta.url), 'utf8');
    const dailyRow = source.slice(source.indexOf('v-for="day in week"'), source.lastIndexOf('</Card>'));
    const nutrition = dailyRow.match(/<p class="([^"]+)">\s*\{\{ day\.consumed_calories \}\}([\s\S]*?)<\/p>/);

    assert.ok(nutrition, 'The row must retain its daily calorie and macro details');
    assert.ok(nutrition[1].split(' ').includes('whitespace-normal'));
    assert.doesNotMatch(nutrition[1], /truncate|overflow-hidden|text-ellipsis|whitespace-nowrap|line-clamp/);
    assert.match(nutrition[2], /day\.effective_target/);
    for (const macro of ['protein_g', 'carbs_g', 'fat_g']) {
        assert.ok(nutrition[2].includes(`Math.round(day.${macro})`), macro);
    }

    assert.match(dailyRow, /<div class="min-w-0 flex-1">\s*<p class="font-semibold">/);
    assert.match(dailyRow, /class="flex shrink-0 flex-col items-end gap-1\.5"/);
    assert.match(dailyRow, /<DayStatusIndicator :status="day\.status"/);
    assert.match(dailyRow, /:aria-current="day\.is_selected \? 'date' : undefined"/);
});
