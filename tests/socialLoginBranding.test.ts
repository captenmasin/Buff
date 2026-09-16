import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const accountSource = readFileSync(new URL('../resources/js/Pages/Account.vue', import.meta.url), 'utf8');
const socialLoginSource = readFileSync(new URL('../resources/js/Components/SocialLoginButtons.vue', import.meta.url), 'utf8');

test('uses branded Google and Apple sign-in buttons everywhere', () => {
    assert.equal(accountSource.match(/<SocialLoginButtons/g)?.length, 2);
    assert.match(socialLoginSource, /#4285F4/);
    assert.match(socialLoginSource, /#34A853/);
    assert.match(socialLoginSource, /#FBBC05/);
    assert.match(socialLoginSource, /#EA4335/);
    assert.match(socialLoginSource, /border-black bg-black text-white/);
    assert.match(socialLoginSource, /Continue with Google/);
    assert.match(socialLoginSource, /Continue with Apple/);
});

test('falls back to browser navigation when native social auth is unavailable', () => {
    assert.match(accountSource, /if \(await Browser\.auth\(url\)\)/);
    assert.match(accountSource, /window\.location\.assign\(url\)/);
});

test('carries the preferred name through social registration', () => {
    assert.match(accountSource, /query\.set\('flow', 'register'\)/);
    assert.match(accountSource, /query\.set\('preferred_name', registerForm\.name\.trim\(\)\)/);
});

test('keeps registration progress and keyboard advancement behind the same validation', () => {
    assert.match(accountSource, /\/ registerSteps\.length\) \* 100/);
    assert.match(accountSource, /if \(registrationNextDisabled\.value \|\| registerForm\.processing\) \{\s+return;/);
});

test('offers confirmed local-device recovery from the signed-out account screen', () => {
    assert.match(accountSource, /v-if="hasDeviceData"[\s\S]*Clear device data/);
    assert.match(accountSource, /:open="clearDataConfirmOpen"[\s\S]*@confirm="confirmClearData"/);
    assert.match(accountSource, /clearDataForm\.delete\('\/account\/local-data'/);
});
