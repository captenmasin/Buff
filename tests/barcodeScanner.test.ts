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

test('shows a viewfinder rectangle for centering barcodes', () => {
    const scannerOverlay = addPageSource.match(/<div v-if="webScannerOpen"[\s\S]*?<AddChooser/)?.[0] ?? '';

    assert.match(scannerOverlay, /aspect-\[5\/3\].*max-w-sm/);
    assert.match(scannerOverlay, /shadow-\[0_0_0_9999px_rgb\(15_17_37_\/_0\.45\)\]/);
    assert.match(addPageSource, /notifyBarcodeFound/);
    assert.match(addPageSource, /buff:toast.*Barcode found/);
    assert.match(scannerOverlay, /Barcode found/);
});
