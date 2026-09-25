import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {stripTypeScriptTypes} from 'node:module';

const source = readFileSync(new URL('../resources/js/Pages/Today.vue', import.meta.url), 'utf8');
const floatingLinesSource = readFileSync(new URL('../resources/js/Components/FloatingLines.vue', import.meta.url), 'utf8');
const checkSource = source.slice(source.indexOf('async function checkSubscriptionPrompt('), source.indexOf('function viewSubscriptionPlans('));

function promptChecker(options: {
    eligible?: boolean;
    entitled?: boolean;
    online?: boolean;
    platform?: 'ios' | 'unsupported';
    post?: () => Promise<unknown>;
} = {}) {
    const subscriptionPromptOpen = {value: false};
    const subscriptionPromptChecking = {value: false};
    const props = {subscriptionPromptEligible: options.eligible ?? true};
    const isToday = {value: true};
    const page = {props: {buff: {account: {id: 'account-1', subscription: {entitled: options.entitled ?? false}}}}};
    let requests = 0;
    const axios = {post: async (url: string) => {
        assert.equal(url, '/subscription/prompt-seen');
        requests++;
        return options.post?.() ?? {data: {claimed: true}};
    }};
    const check = new Function('props', 'isToday', 'subscriptionPromptOpen', 'subscriptionPromptChecking', 'page', 'axios', 'subscriptionPlatform', 'navigator', `
        ${stripTypeScriptTypes(checkSource)}
        return checkSubscriptionPrompt;
    `)(props, isToday, subscriptionPromptOpen, subscriptionPromptChecking, page, axios, async () => options.platform ?? 'ios', {onLine: options.online ?? true}) as () => Promise<void>;

    return {check, open: subscriptionPromptOpen, checking: subscriptionPromptChecking, page, get requests() { return requests; }};
}

test('claims once before showing the full-screen Buff+ prompt', async () => {
    const prompt = promptChecker();

    await prompt.check();
    await prompt.check();

    assert.equal(prompt.requests, 1);
    assert.equal(prompt.open.value, true);
    assert.match(source, /labelled-by="subscription-prompt-title"[\s\S]*?h-dvh max-h-dvh w-screen/);
    assert.match(source, /bg-brand-acid[\s\S]*?<FloatingLines/);
    assert.match(source, /<div class="relative -m-5 flex min-h-dvh flex-col overflow-x-hidden overflow-y-auto bg-brand-acid[^"]*text-black">/);
    assert.doesNotMatch(source, /rounded-3xl bg-brand-night p-7 text-white/);
    assert.match(source, /defineAsyncComponent\(\(\) => import\('\.\.\/Components\/FloatingLines\.vue'\)\)/);
    assert.match(source, /showSubscriptionLines\.value = open && !document\.documentElement\.hasAttribute\('data-reduce-motion'\)/);
    assert.match(source, /v-if="showSubscriptionLines" class="pointer-events-none absolute inset-0"/);
    assert.match(source, /const subscriptionLineGradient = \['#ffffff'\]/);
    assert.match(source, /<FloatingLines[\s\S]*?opacity-65[\s\S]*?:enabled-waves="subscriptionLineWaves"[\s\S]*?:middle-wave-position="subscriptionLinePosition"[\s\S]*?:animation-speed="1"[\s\S]*?:interactive="false"/);
    assert.match(floatingLinesSource, /new WebGLRenderer\(/);
    assert.doesNotMatch(source, /subscription-prompt-lines/);
    assert.match(source, /class="w-full bg-white text-black hover:bg-white\/90" @click="viewSubscriptionPlans"/);
    assert.match(source, /@click="viewSubscriptionPlans">See Buff\+ plans/);
    assert.match(source, /@click="subscriptionPromptOpen = false">Maybe later/);
});

test('skips ineligible, subscribed, offline, and unsupported accounts', async () => {
    for (const options of [{eligible: false}, {entitled: true}, {online: false}, {platform: 'unsupported' as const}]) {
        const prompt = promptChecker(options);
        await prompt.check();
        assert.equal(prompt.requests, 0);
        assert.equal(prompt.open.value, false);
    }
});

test('does not show an already claimed prompt and retries after a network failure', async () => {
    const alreadySeen = promptChecker({post: async () => ({data: {claimed: false}})});
    await alreadySeen.check();
    assert.equal(alreadySeen.open.value, false);

    let attempts = 0;
    const retry = promptChecker({post: async () => {
        if (++attempts === 1) {
            throw new Error('Offline');
        }

        return {data: {claimed: true}};
    }});
    await retry.check();
    assert.equal(retry.open.value, false);
    assert.equal(retry.checking.value, false);
    await retry.check();
    assert.equal(retry.requests, 2);
    assert.equal(retry.open.value, true);
});
