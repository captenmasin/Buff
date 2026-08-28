import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const addPageSource = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

test('shows a contrasting cancel button on the barcode scanner', () => {
    const scannerOverlay = addPageSource.match(/<div v-if="webScannerOpen"[\s\S]*?<AddChooser/)?.[0] ?? '';

    assert.match(scannerOverlay, /bg-foreground text-background/);
    assert.match(scannerOverlay, /bg-background\/10 text-background[^\"]*" aria-label="Close scanner" @click="stopWebScan"/);
    assert.doesNotMatch(scannerOverlay, /text-primary-foreground/);
});
