import assert from 'node:assert/strict';
import test from 'node:test';
import { tooltipLabelDate, tooltipSeriesEntries, unwrapTooltipDatum } from '../resources/js/chartTooltip.ts';

test('unwraps a nested Unovis datum without dropping the weigh-in', () => {
    const payload = unwrapTooltipDatum({
        data: { date: new Date(2026, 7, 20), weight: 82.4 },
    });

    assert.equal(payload.weight, 82.4);
});

test('keeps a flat chart row as the tooltip payload', () => {
    const payload = unwrapTooltipDatum({ date: new Date(2026, 7, 20), weight: 82.4, goal: 80 });

    assert.equal(payload.weight, 82.4);
    assert.equal(payload.goal, 80);
});

test('returns an empty payload when Crosshair has no datum', () => {
    assert.deepEqual(unwrapTooltipDatum(undefined), {});
    assert.deepEqual(unwrapTooltipDatum(null), {});
});

test('shows numeric series from the chart config and skips dates', () => {
    const entries = tooltipSeriesEntries(
        { date: new Date(2026, 7, 20), weight: 82.4, goal: 80 },
        { weight: { label: 'Weight', color: 'var(--primary)' } },
    );

    assert.deepEqual(entries.map(({ key, value }) => ({ key, value })), [
        { key: 'weight', value: 82.4 },
    ]);
});

test('omits a series when that day has no value', () => {
    const entries = tooltipSeriesEntries(
        { date: new Date(2026, 4, 22), goal: 80 },
        { weight: { label: 'Weight', color: 'var(--primary)' } },
    );

    assert.equal(entries.length, 0);
});

test('labels the tooltip with the weigh-in date instead of the cursor x', () => {
    const date = new Date(2026, 7, 18);

    assert.equal(tooltipLabelDate({ date, weight: 82.4 }, Date.UTC(2026, 0, 1)), date);
});
