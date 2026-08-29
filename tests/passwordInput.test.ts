import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const passwordInputSource = readFileSync(new URL('../resources/js/Components/PasswordInput.vue', import.meta.url), 'utf8');
const passwordPages = [
    '../resources/js/Pages/Account.vue',
    '../resources/js/Pages/Settings.vue',
    '../resources/js/Pages/Settings/Password.vue',
].map((path) => readFileSync(new URL(path, import.meta.url), 'utf8')).join('\n');

test('lets users reveal every password field without submitting its form', () => {
    const passwordFields = [...passwordPages.matchAll(/<PasswordInput\b[^>]*>/g)];

    assert.match(passwordInputSource, /:type="passwordVisible \? 'text' : 'password'"/);
    assert.match(passwordInputSource, /type="button"/);
    assert.match(passwordInputSource, /passwordVisible \? 'Hide password' : 'Show password'/);
    assert.doesNotMatch(passwordPages, /<Input[^>]+type="password"/);
    assert.ok(passwordFields.length > 0);
    assert.ok(passwordFields.every(([field]) => /\bid=/.test(field)));
});
