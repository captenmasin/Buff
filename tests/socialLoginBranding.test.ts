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
