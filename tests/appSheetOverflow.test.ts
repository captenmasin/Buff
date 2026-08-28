import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appSheetSource = readFileSync(new URL('../resources/js/Components/AppSheet.vue', import.meta.url), 'utf8');
const sheetContentSource = readFileSync(new URL('../resources/js/Components/ui/sheet/SheetContent.vue', import.meta.url), 'utf8');
const appStyles = readFileSync(new URL('../resources/css/app.css', import.meta.url), 'utf8');

test('keeps drawers and modals scrollable on short viewports', () => {
    assert.match(appSheetSource, /max-h-\[88dvh\][^']*overflow-y-auto[^']*overscroll-contain/);
    assert.match(appSheetSource, /max-h-\[calc\(100dvh-2rem\)\][^']*overflow-y-auto[^']*overscroll-contain/);
});

test('centers drawers as dialogs on desktop', () => {
    assert.match(appStyles, /@media \(width >= 40rem\)[\s\S]*?\.bottom-drawer \{[\s\S]*?inset: 1rem 0 1rem 16rem;[\s\S]*?height: fit-content;[\s\S]*?margin: auto;[\s\S]*?border-radius: var\(--radius-xl\);/);
});

test('hides scrollbars without disabling scrolling', () => {
    assert.match(appStyles, /\* \{[\s\S]*?scrollbar-width: none;/);
    assert.match(appStyles, /\*::-webkit-scrollbar \{[\s\S]*?display: none;/);
});

test('transitions the translate property used to slide sheets', () => {
    assert.match(sheetContentSource, /transition-\[translate,transform,opacity\]/);
});
