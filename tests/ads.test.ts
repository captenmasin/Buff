import assert from 'node:assert/strict';
import test from 'node:test';
import type {AdmobApi, BannerResult, ConsentResult, Policy, TrackingStatus} from '../native-plugins/admob/resources/js/admob.js';
import {createAdCoordinator, hideAppShellBanner, isAdRoute, type AdAudience} from '../resources/js/ads.ts';

const nonEntitled = {data: {subscription: {entitled: false}}};

function fakeAdmob(options: {
    consent?: ConsentResult;
    enabled?: boolean;
    requestConsent?: () => Promise<ConsentResult>;
    trackingStatus?: TrackingStatus;
    loadResults?: BannerResult[];
} = {}) {
    const calls: string[] = [];
    const policies: Policy[] = [];
    const loadResults = [...(options.loadResults ?? [{ok: true, height: 50}])];
    const api: AdmobApi = {
        async enabled() {
            calls.push('enabled');
            return options.enabled ?? true;
        },
        async configurePolicy(policy) {
            calls.push('configure');
            policies.push({...policy});
            return {ok: true};
        },
        async initialize() {
            calls.push('initialize');
            return {ok: true};
        },
        banner() {
            return {
                async load() {
                    calls.push('load');
                    return loadResults.shift() ?? {ok: true, height: 50};
                },
                async show() {
                    calls.push('show');
                    return {ok: true};
                },
                async hide() {
                    calls.push('hide');
                    return {ok: true};
                },
            };
        },
        ump: {
            async requestInfo() {
                calls.push('ump:info');
                return options.requestConsent?.()
                    ?? options.consent
                    ?? {ok: true, status: 'obtained', canRequestAds: true};
            },
            async showForm() {
                calls.push('ump:form');
                return {ok: true, status: 'obtained', canRequestAds: true};
            },
            async canRequestAds() {
                return true;
            },
            async status() {
                return 'obtained';
            },
            async privacyOptionsStatus() {
                return 'not_required';
            },
            async showPrivacyOptionsForm() {
                return {ok: true};
            },
        },
        att: {
            async status() {
                calls.push('att:status');
                return options.trackingStatus ?? 'authorized';
            },
            async request() {
                calls.push('att:request');
                return {ok: true, status: options.trackingStatus ?? 'authorized'};
            },
        },
    };

    return {api, calls, policies};
}

function harness(options: {
    entitled?: unknown;
    platform?: 'ios' | 'android' | 'unsupported';
    audience?: AdAudience;
    admob?: ReturnType<typeof fakeAdmob>;
    refresh?: () => Promise<unknown>;
} = {}) {
    const admob = options.admob ?? fakeAdmob();
    const heights: number[] = [];
    let bridgeLoads = 0;
    const coordinator = createAdCoordinator({
        platform: async () => options.platform ?? 'android',
        loadBridge: async () => {
            bridgeLoads++;
            return admob.api;
        },
        refreshSubscription: options.refresh ?? (async () => Object.hasOwn(options, 'entitled')
            ? {data: {subscription: {entitled: options.entitled}}}
            : nonEntitled),
        setBannerHeight: (height) => heights.push(height),
    });
    const input = {
        account: {id: 'account-1'},
        url: '/',
        audience: options.audience ?? 'adult',
        bottomOffset: 64,
    } as const;

    return {admob, coordinator, heights, input, bridgeLoads: () => bridgeLoads};
}

test('allowlists only the three exact top-level routes', () => {
    assert.equal(isAdRoute('/'), true);
    assert.equal(isAdRoute('/goals?tab=macros'), true);
    assert.equal(isAdRoute('/progress'), true);
    assert.equal(isAdRoute('/goals/edit'), false);
    assert.equal(isAdRoute('/settings'), false);
    assert.equal(isAdRoute('/account/login'), false);
    assert.equal(isAdRoute('/account/register'), false);
});

test('auth screens can hide a stale native banner without loading AdMob on web', async () => {
    const admob = fakeAdmob();
    let bridgeLoads = 0;
    const loadBridge = async () => {
        bridgeLoads++;

        return admob.api;
    };

    await hideAppShellBanner({platform: async () => 'ios', loadBridge});
    await hideAppShellBanner({platform: async () => 'unsupported', loadBridge});

    assert.deepEqual(admob.calls, ['hide']);
    assert.equal(bridgeLoads, 1);
});

test('subscribed and unknown entitlement states never load the native bridge', async () => {
    for (const entitled of [true, undefined, null]) {
        const state = harness({entitled});

        await state.coordinator.reconcile(state.input);

        assert.equal(state.bridgeLoads(), 0);
        assert.deepEqual(state.admob.calls, []);
        assert.equal(state.heights.at(-1), 0);
    }
});

test('a later subscription hides the existing banner without another load', async () => {
    let refreshes = 0;
    const state = harness({refresh: async () => {
        refreshes++;

        return refreshes === 1
            ? nonEntitled
            : {data: {subscription: {entitled: true}}};
    }});

    await state.coordinator.reconcile(state.input);
    await state.coordinator.reconcile(state.input);

    assert.equal(state.admob.calls.filter((call) => call === 'load').length, 1);
    assert.equal(state.admob.calls.filter((call) => call === 'show').length, 1);
    assert.equal(state.admob.calls.at(-1), 'hide');
    assert.equal(state.heights.at(-1), 0);
});

test('refresh failures hide and fail closed before SDK initialization', async () => {
    const state = harness({refresh: async () => { throw new Error('offline'); }});

    await state.coordinator.reconcile(state.input);

    assert.equal(state.bridgeLoads(), 0);
    assert.equal(state.heights.at(-1), 0);
});

test('the master switch stops before policy, consent, or SDK initialization', async () => {
    const admob = fakeAdmob({enabled: false});
    const state = harness({admob});

    await state.coordinator.reconcile(state.input);

    assert.deepEqual(admob.calls, ['enabled', 'hide']);
});

test('a visible banner is hidden while fresh eligibility is pending', async () => {
    let finishRefresh: (value: unknown) => void = () => {};
    let refreshes = 0;
    const state = harness({refresh: () => {
        refreshes++;

        if (refreshes === 1) {
            return Promise.resolve(nonEntitled);
        }

        return new Promise((resolve) => {
            finishRefresh = resolve;
        });
    }});

    await state.coordinator.reconcile(state.input);
    const pending = state.coordinator.reconcile(state.input);
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(state.heights.at(-1), 0);
    assert.equal(state.admob.calls.at(-1), 'hide');

    finishRefresh(nonEntitled);
    await pending;
});

test('web builds return before subscription or AdMob work', async () => {
    let refreshes = 0;
    const state = harness({
        platform: 'unsupported',
        refresh: async () => {
            refreshes++;
            return nonEntitled;
        },
    });

    await state.coordinator.reconcile(state.input);

    assert.equal(refreshes, 0);
    assert.equal(state.bridgeLoads(), 0);
});

test('an explicit non-entitled adult completes consent before initialization and banner display', async () => {
    const admob = fakeAdmob({consent: {ok: true, status: 'required', canRequestAds: false}});
    const state = harness({admob});

    await state.coordinator.reconcile(state.input);

    assert.deepEqual(state.admob.calls, [
        'enabled',
        'configure',
        'ump:info',
        'ump:form',
        'configure',
        'initialize',
        'load',
        'show',
    ]);
    assert.equal(state.heights.at(-1), 50);
});

test('unknown consent never initializes even if a stale SDK flag says ads are allowed', async () => {
    const admob = fakeAdmob({consent: {ok: true, status: 'unknown', canRequestAds: true}});
    const state = harness({admob});

    await state.coordinator.reconcile(state.input);

    assert.equal(admob.calls.includes('initialize'), false);
    assert.equal(admob.calls.includes('load'), false);
    assert.equal(state.heights.at(-1), 0);
});

test('teen-safe audience policy is under-age and non-personalized without ATT', async () => {
    const state = harness({platform: 'ios', audience: 'teen'});

    await state.coordinator.reconcile(state.input);

    assert.equal(state.admob.policies.every((policy) => policy.underAgeOfConsent && policy.nonPersonalized), true);
    assert.equal(state.admob.calls.some((call) => call.startsWith('att:')), false);
});

test('adult iOS ATT denial reapplies a non-personalized policy', async () => {
    const admob = fakeAdmob({trackingStatus: 'denied'});
    const state = harness({platform: 'ios', admob});

    await state.coordinator.reconcile(state.input);

    assert.equal(admob.policies[0].nonPersonalized, false);
    assert.equal(admob.policies.at(-1)?.nonPersonalized, true);
    assert.ok(admob.calls.indexOf('att:status') < admob.calls.indexOf('initialize'));
});

test('excluded routes, logout, and account switches hide and force fresh eligibility', async () => {
    let refreshes = 0;
    const state = harness({refresh: async () => {
        refreshes++;
        return nonEntitled;
    }});

    await state.coordinator.reconcile(state.input);
    await state.coordinator.reconcile({...state.input, url: '/settings'});
    await state.coordinator.reconcile({...state.input, account: null});
    await state.coordinator.reconcile({...state.input, account: {id: 'account-2'}});

    assert.equal(refreshes, 2);
    assert.ok(state.admob.calls.filter((call) => call === 'hide').length >= 3);
});

test('banner height reserves space and load failure clears it', async () => {
    const admob = fakeAdmob({loadResults: [
        {ok: true, height: 61.2},
        {ok: false, error: 'no_fill'},
    ]});
    const state = harness({admob});

    await state.coordinator.reconcile(state.input);
    assert.equal(state.heights.at(-1), 62);

    await state.coordinator.reconcile(state.input);
    assert.equal(state.heights.at(-1), 0);
});

test('concurrent reconciliations for one account share one refresh', async () => {
    let finishRefresh: (value: unknown) => void = () => {};
    let refreshes = 0;
    const state = harness({refresh: () => {
        refreshes++;
        return new Promise((resolve) => {
            finishRefresh = resolve;
        });
    }});

    const first = state.coordinator.reconcile(state.input);
    const second = state.coordinator.reconcile(state.input);
    await new Promise((resolve) => setImmediate(resolve));
    finishRefresh(nonEntitled);
    await Promise.all([first, second]);

    assert.equal(refreshes, 1);
});

test('an account switch waits for the previous consent path to finish', async () => {
    let finishFirstRefresh: (value: unknown) => void = () => {};
    let refreshes = 0;
    const state = harness({refresh: () => {
        refreshes++;

        if (refreshes === 1) {
            return new Promise((resolve) => {
                finishFirstRefresh = resolve;
            });
        }

        return Promise.resolve(nonEntitled);
    }});

    const first = state.coordinator.reconcile(state.input);
    await new Promise((resolve) => setImmediate(resolve));
    const second = state.coordinator.reconcile({...state.input, account: {id: 'account-2'}});
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(refreshes, 1);
    finishFirstRefresh(nonEntitled);
    await Promise.all([first, second]);

    assert.equal(refreshes, 2);
    assert.equal(state.bridgeLoads(), 1);
});

test('an audience change supersedes in-flight policy work for the same account', async () => {
    let finishFirstRefresh: (value: unknown) => void = () => {};
    let refreshes = 0;
    const state = harness({refresh: () => {
        refreshes++;

        if (refreshes === 1) {
            return new Promise((resolve) => {
                finishFirstRefresh = resolve;
            });
        }

        return Promise.resolve(nonEntitled);
    }});

    const adult = state.coordinator.reconcile(state.input);
    await new Promise((resolve) => setImmediate(resolve));
    const teen = state.coordinator.reconcile({...state.input, audience: 'teen'});
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(refreshes, 1);
    finishFirstRefresh(nonEntitled);
    await Promise.all([adult, teen]);

    assert.equal(refreshes, 2);
    assert.equal(state.admob.policies.every((policy) => policy.underAgeOfConsent), true);
});

test('native policy and consent work is serialized across coordinator instances', async () => {
    let finishConsent: (value: ConsentResult) => void = () => {};
    const firstAdmob = fakeAdmob({requestConsent: () => new Promise((resolve) => {
        finishConsent = resolve;
    })});
    const secondAdmob = fakeAdmob();
    const firstState = harness({admob: firstAdmob});
    const secondState = harness({admob: secondAdmob});

    const first = firstState.coordinator.reconcile(firstState.input);
    await new Promise((resolve) => setImmediate(resolve));
    const second = secondState.coordinator.reconcile(secondState.input);
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(secondState.bridgeLoads(), 0);
    finishConsent({ok: true, status: 'obtained', canRequestAds: true});
    await Promise.all([first, second]);

    assert.equal(secondState.bridgeLoads(), 1);
});
