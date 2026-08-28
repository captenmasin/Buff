import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { formatDisplayDate, parseLocalDate } from '../resources/js/dateFormat.ts';
import { buildBodyFatChartData, buildGoalLine, buildWeightChartData, chartSummary, chartXDomain, chartYDomain, deltaTone } from '../resources/js/progressChart.ts';

const metrics = [
    { date: '2026-08-20', weight: 82.4, bodyFat: 18.2 },
    { date: '2026-08-10', weight: 83, bodyFat: null },
    { date: '2026-07-01', weight: 84.2, bodyFat: 19 },
];

test('parses calendar dates at local midnight', () => {
    const date = parseLocalDate('2026-08-20');

    assert.equal(date.getFullYear(), 2026);
    assert.equal(date.getMonth(), 7);
    assert.equal(date.getDate(), 20);
    assert.equal(date.getHours(), 0);
});

test('formats display dates without a year when requested', () => {
    assert.equal(formatDisplayDate('2026-08-23', { weekday: 'short', year: false }), 'Sun 23 August');
});

test('uses timestamps for the selected window so Unovis can rescale on range changes', () => {
    const ninety = chartXDomain('2026-05-23', '2026-08-20');
    const thirty = chartXDomain('2026-07-22', '2026-08-20');

    assert.equal(thirty[1] - thirty[0], 29 * 24 * 60 * 60 * 1000);
    assert.ok(ninety[0] < thirty[0]);
    assert.equal(ninety[1], thirty[1]);
});

test('keeps the weight series on calendar dates across the selected window', () => {
    const rows = buildWeightChartData(
        metrics.map(({ date, weight }) => ({ date, weight })),
        '2026-05-22',
        '2026-08-20',
        80,
    );
    const weighIns = rows.filter((row) => row.weight !== undefined);

    assert.equal(weighIns.length, 3);
    assert.equal(weighIns[0].date.getTime(), parseLocalDate('2026-07-01').getTime());
    assert.equal(weighIns[0].weight, 84.2);
    assert.equal('trend' in weighIns[0], false);
    assert.equal(weighIns[2].date.getTime(), parseLocalDate('2026-08-20').getTime());
    assert.deepEqual(chartXDomain('2026-05-22', '2026-08-20'), [
        parseLocalDate('2026-05-22').getTime(),
        parseLocalDate('2026-08-20').getTime(),
    ]);
});

test('does not invent weigh-ins to draw the goal line', () => {
    const rows = buildWeightChartData(
        metrics.map(({ date, weight }) => ({ date, weight })),
        '2026-05-22',
        '2026-08-20',
        80,
    );

    assert.equal(rows.length, 3);
    assert.equal(rows[0].date.getTime(), parseLocalDate('2026-07-01').getTime());
    assert.equal(rows[0].weight, 84.2);
    assert.equal(rows[0].goal, 80);
    assert.equal(rows.every((row) => row.weight !== undefined), true);
});

test('draws the goal as a two-point line across the selected window', () => {
    const rows = buildGoalLine('2026-05-22', '2026-08-20', 80);

    assert.equal(rows.length, 2);
    assert.equal(rows[0].date.getTime(), parseLocalDate('2026-05-22').getTime());
    assert.equal(rows[0].goal, 80);
    assert.equal(rows[0].weight, undefined);
    assert.equal(rows[1].date.getTime(), parseLocalDate('2026-08-20').getTime());
    assert.equal(rows[1].goal, 80);
});

test('omits the goal series when no target is set', () => {
    const rows = buildWeightChartData(
        metrics.map(({ date, weight }) => ({ date, weight })),
        '2026-08-10',
        '2026-08-20',
        null,
    );

    assert.equal(rows.length, 3);
    assert.equal(rows.every((row) => row.goal === undefined), true);
});

test('starts the y-axis slightly below the goal when the goal is the lowest value', () => {
    const [min, max] = chartYDomain([82.4, 83, 84.2], 80);

    assert.equal(max, undefined);
    assert.ok(min !== undefined);
    assert.ok(min < 80);
    assert.ok(min > 0);
});

test('keeps the y-axis below measured values that undershoot the goal', () => {
    const [min] = chartYDomain([78.5, 79.2], 80);

    assert.ok(min !== undefined);
    assert.ok(min < 78.5);
});

test('leaves the y-axis unconstrained when no goal is set', () => {
    assert.deepEqual(chartYDomain([82.4, 83], null), [undefined, undefined]);
});

test('does not let the y-axis drop below zero', () => {
    const [min] = chartYDomain([1.2], 1);

    assert.equal(min, 0);
});

test('omits missing body-fat days instead of drawing zeros', () => {
    const rows = buildBodyFatChartData(
        metrics.map(({ date, bodyFat }) => ({ date, bodyFat })),
        '2026-05-22',
        '2026-08-20',
        15,
    );
    const measured = rows.filter((row) => row.bodyFat !== undefined);

    assert.equal(measured.length, 2);
    assert.equal(measured[0].bodyFat, 19);
    assert.equal(measured[1].bodyFat, 18.2);
    assert.equal(rows[0].date.getTime(), parseLocalDate('2026-07-01').getTime());
    assert.equal(rows[0].bodyFat, 19);
    assert.equal(rows[0].goal, 15);
});

test('summarises a chart from first reading to last, including the goal', () => {
    const rows = buildWeightChartData(
        metrics.map(({ date, weight }) => ({ date, weight })),
        '2026-05-22',
        '2026-08-20',
        80,
    );

    assert.equal(chartSummary(rows, 'weight', ' kg', 80), 'Started at 84.2 kg, now 82.4 kg, vs 80 kg goal.');
    assert.equal(chartSummary(rows, 'weight', ' kg', null), 'Started at 84.2 kg, now 82.4 kg.');
});

test('omits a chart summary when there are no readings', () => {
    assert.equal(chartSummary([], 'bodyFat', '%', 15), '');
});

test('renders chart summaries as a readable callout under the chart', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.equal(source.match(/rounded-xl bg-secondary px-3.5 py-2.5 text-sm font-semibold tabular-nums text-foreground/g)?.length, 2);
    assert.doesNotMatch(source, /text-sm text-muted-foreground">\{\{ weightChartSummary \}\}/);
});

test('renders weight and body-fat trends as a swipeable carousel', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.match(source, /data-chart-carousel/);
    assert.match(source, /snap-x snap-mandatory gap-5 overflow-x-auto/);
    assert.match(source, /slide\.offsetLeft - firstSlide\.offsetLeft/);
    assert.match(source, /aria-label="Weight chart"/);
    assert.match(source, /aria-label="Body fat chart"/);
});

test('colours a weight change by whether it moves toward the target', () => {
    assert.equal(deltaTone(-0.6, 82.4, 80), 'text-success-foreground');
    assert.equal(deltaTone(0.4, 82.4, 80), 'text-destructive');
    assert.equal(deltaTone(0.5, 70, 80), 'text-success-foreground');
    assert.equal(deltaTone(-0.4, 70, 80), 'text-destructive');
    assert.equal(deltaTone(0.4, 80, 80), 'text-foreground');
    assert.equal(deltaTone(null, 82.4, 80), 'text-foreground');
});
