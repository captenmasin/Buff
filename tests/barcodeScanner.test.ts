import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const addPageSource = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

test('submits a manual barcode with Enter and leaves lookup errors next to the editable input', () => {
    const manualEntry = addPageSource.match(/<form[^>]*@submit.prevent="lookup\(\)"[\s\S]*?<\/form>/)?.[0] ?? '';

    assert.match(manualEntry, /enterkeyhint="search"/);
    assert.match(manualEntry, /:aria-describedby="lookupError \? 'manual-barcode-error'/);
    assert.match(manualEntry, /<Button type="submit"/);
    assert.match(manualEntry, /id="manual-barcode-error"[^>]*role="alert"/);
});

test('shows a contrasting cancel button on the barcode scanner', () => {
    const scannerOverlay = addPageSource.match(/<FocusScope v-if="webScannerOpen"[\s\S]*?<\/FocusScope>/)?.[0] ?? '';

    assert.match(scannerOverlay, /bg-foreground text-background/);
    assert.match(scannerOverlay, /bg-background\/10 text-background[^\"]*" aria-label="Close scanner" @click="stopWebScan"/);
    assert.doesNotMatch(scannerOverlay, /text-primary-foreground/);
});

test('shows a viewfinder rectangle for centering barcodes', () => {
    const scannerOverlay = addPageSource.match(/<FocusScope v-if="webScannerOpen"[\s\S]*?<\/FocusScope>/)?.[0] ?? '';

    assert.match(scannerOverlay, /aspect-\[5\/3\].*max-w-sm/);
    assert.match(scannerOverlay, /shadow-\[0_0_0_9999px_rgb\(15_17_37_\/_0\.45\)\]/);
    assert.match(addPageSource, /notifyBarcodeFound/);
    assert.match(addPageSource, /buff:toast.*Barcode found/);
    assert.match(scannerOverlay, /Barcode found/);
});

test('opens a live camera for nutrition labels and offers a photo picker as a separate action', () => {
    const cameraHandler = addPageSource.slice(addPageSource.indexOf('async function openLabelCamera()'), addPageSource.indexOf('function captureNutritionLabel()'));

    assert.match(addPageSource, /labelCameraOpen \? 'Scan nutrition label'/);
    assert.match(cameraHandler, /getUserMedia\([\s\S]*?video\.srcObject = stream/);
    assert.match(addPageSource, /@click="captureNutritionLabel"[\s\S]*?Take photo/);
    assert.match(addPageSource, /@click="labelPhotoInput\?\.click\(\)"[\s\S]*?Choose photo/);
});

test('links both locked label scan entry points to the Add paywall', () => {
    const labelLaunchers = [...addPageSource.matchAll(/<Button\b[^>]*@click="[^"]*openLabelCamera[^"]*"[^>]*>[\s\S]*?<\/Button>/g)];

    assert.equal(labelLaunchers.length, 2);
    for (const [button] of labelLaunchers) {
        assert.match(button, /:as="subscriptionActive \? 'button' : Link"/);
        assert.match(button, /:href="subscriptionActive \? undefined : addModeUrl\('label'\)"/);
        assert.match(button, /Scan label\s*<span v-if="!subscriptionActive"[^>]*>Buff\+ required<\/span>/);
    }
    assert.match(labelLaunchers[0][0], /@click="subscriptionActive \? openLabelCamera\(\) : stopWebScan\(\)"/);
});

test('blocks the label camera and photo submission without Buff+', () => {
    assert.match(addPageSource, /async function openLabelCamera\(\) \{\s*if \(!subscriptionActive\.value\) return;/);
    assert.match(addPageSource, /async function scanNutritionLabel\([^]*?\{\s*if \(!subscriptionActive\.value \|\| labelLoading\.value\) return;/);
    assert.match(addPageSource, /watch\(subscriptionActive,[^]*?if \(labelCameraOpen\.value\) stopWebScan\(\);\s*labelRequest\?\.abort\(\);/);
});
