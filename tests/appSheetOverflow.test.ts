import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appSheetSource = readFileSync(new URL('../resources/js/Components/AppSheet.vue', import.meta.url), 'utf8');

test('keeps drawers and modals scrollable on short viewports', () => {
    assert.match(appSheetSource, /max-h-\[88dvh\][^']*overflow-y-auto[^']*overscroll-contain/);
    assert.match(appSheetSource, /max-h-\[calc\(100dvh-2rem\)\][^']*overflow-y-auto[^']*overscroll-contain/);
});
