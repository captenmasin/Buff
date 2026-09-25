import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { formatChartTickDate, formatDisplayDate, parseLocalDate } from '../resources/js/dateFormat.ts';
import { buildBodyFatChartData, buildGoalLine, buildWeightChartData, chartSummary, chartTickFormatter, chartTickValues, chartXDomain, chartYDomain, deltaTone } from '../resources/js/progressChart.ts';

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

test('does not generate duplicate day ticks for short chart domains', () => {
    assert.deepEqual(chartTickValues(chartXDomain('2026-08-31', '2026-08-31')).map(formatChartTickDate), ['31 Aug']);
    assert.deepEqual(chartTickValues(chartXDomain('2026-08-30', '2026-08-31')).map(formatChartTickDate), ['30 Aug', '31 Aug']);
    assert.deepEqual(chartTickValues(chartXDomain('2026-09-03', '2026-09-04')).map(formatChartTickDate), ['3 Sept', '4 Sept']);
    assert.deepEqual(chartTickValues(chartXDomain('2026-09-02', '2026-09-04')).map(formatChartTickDate), ['2 Sept', '3 Sept', '4 Sept']);
    assert.deepEqual(chartTickValues(chartXDomain('2026-08-01', '2026-08-31')).map(formatChartTickDate), ['1 Aug', '11 Aug', '21 Aug', '31 Aug']);
});

test('keeps date ticks at local midnight across short and long daylight-saving windows', () => {
    const chartModule = new URL('../resources/js/progressChart.ts', import.meta.url).href;
    const dateModule = new URL('../resources/js/dateFormat.ts', import.meta.url).href;
    const result = execFileSync(process.execPath, ['--input-type=module', '-e', `
        import { chartTickValues, chartXDomain } from ${JSON.stringify(chartModule)};
        import { formatChartTickDate } from ${JSON.stringify(dateModule)};
        const ranges = [
            ['2026-03-29', '2026-03-30'],
            ['2026-03-28', '2026-03-30'],
            ['2026-10-24', '2026-10-26'],
            ['2026-03-01', '2026-03-31'],
        ];
        console.log(JSON.stringify(ranges.map(([start, end]) => chartTickValues(chartXDomain(start, end))
            .map(value => [formatChartTickDate(value), new Date(value).getHours()]))));
    `], { env: { ...process.env, TZ: 'Europe/London' }, encoding: 'utf8' });

    assert.deepEqual(JSON.parse(result), [
        [['29 Mar', 0], ['30 Mar', 0]],
        [['28 Mar', 0], ['29 Mar', 0], ['30 Mar', 0]],
        [['24 Oct', 0], ['25 Oct', 0], ['26 Oct', 0]],
        [['1 Mar', 0], ['11 Mar', 0], ['21 Mar', 0], ['31 Mar', 0]],
    ]);
});

test('disambiguates years on cross-year axes while keeping within-year labels compact', () => {
    const multiYear = chartXDomain('2021-01-01', '2024-01-01');
    const newYear = chartXDomain('2026-12-31', '2027-01-01');
    const withinYear = chartXDomain('2026-09-03', '2026-09-04');

    assert.deepEqual(chartTickValues(multiYear).map(chartTickFormatter(multiYear)), ['1 Jan 2021', '1 Jan 2022', '1 Jan 2023', '1 Jan 2024']);
    assert.deepEqual(chartTickValues(newYear).map(chartTickFormatter(newYear)), ['31 Dec 2026', '1 Jan 2027']);
    assert.deepEqual(chartTickValues(withinYear).map(chartTickFormatter(withinYear)), ['3 Sept', '4 Sept']);
});

test('supplies explicit calendar ticks to the chart instead of an approximate numeric count', () => {
    const source = readFileSync(new URL('../resources/js/Components/ProgressTrendChart.vue', import.meta.url), 'utf8');

    assert.match(source, /const xTickValues = computed\(\(\) => chartTickValues\(props\.xDomain\)\)/);
    assert.match(source, /<VisAxis\s+type="x"[^>]+:tick-values="xTickValues"/);
    assert.match(source, /const xTickFormat = computed\(\(\) => chartTickFormatter\(props\.xDomain\)\)/);
    assert.match(source, /<VisAxis\s+type="x"[^>]+:tick-format="xTickFormat"/);
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

test('does not render the started-at chart summary callout on the progress page', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.doesNotMatch(source, /weightChartSummary/);
    assert.doesNotMatch(source, /bodyFatChartSummary/);
    assert.doesNotMatch(source, /chartSummary/);
    assert.doesNotMatch(source, /Started at/);
});

test('switches weight and body-fat trends with buttons instead of a swipe carousel', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Progress.vue', import.meta.url), 'utf8');

    assert.doesNotMatch(source, /data-chart-carousel/);
    assert.doesNotMatch(source, /snap-x snap-mandatory/);
    assert.doesNotMatch(source, /touch-pan-x/);
    assert.match(source, /aria-label="Trend chart"/);
    assert.match(source, /activeChart === 0 \|\| !hasBodyFatChart/);
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
