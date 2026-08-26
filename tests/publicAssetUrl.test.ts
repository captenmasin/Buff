import assert from 'node:assert/strict';
import test from 'node:test';
import { publicAssetUrl } from '../resources/js/publicAssetUrl.ts';

test('uses the iOS asset prefix only inside the NativePHP webview', () => {
    assert.equal(publicAssetUrl('/icon.png', 'php:'), '/_assets/icon.png');
    assert.equal(publicAssetUrl('/logo.svg', 'https:'), '/logo.svg');
    assert.equal(publicAssetUrl('logo.svg', 'https:'), '/logo.svg');
});
