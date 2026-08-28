import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

test('uses separate camera capture and photo library inputs', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

    assert.match(source, /ref="photoCameraInput"[^>]+capture="environment"/);
    assert.match(source, /ref="photoLibraryInput"[^>]+multiple/);
    assert.match(source, /@click="photoCameraInput\?\.click\(\)"[^>]*>[\s\S]*?Take photo/);
    assert.match(source, /@click="photoLibraryInput\?\.click\(\)"[^>]*>[\s\S]*?Choose photo/);
});

test('shows a loading state while preparing photo previews', () => {
    const source = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

    assert.match(source, /photoProcessing\.value = true;[\s\S]*?finally \{[\s\S]*?photoProcessing\.value = false;/);
    assert.match(source, /v-if="photoProcessing"[^>]+role="status"[^>]+aria-live="polite"/);
    assert.match(source, /Preparing photo…/);
});
