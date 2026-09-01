import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {
    completeSubscriptionConfiguration,
    isSubscriptionActive,
    managementUrl,
    nativeError,
    normalizeOffering,
    subscriptionPackageButtonLabel,
    subscriptionPlatform,
    subscriptionEvents,
} from '../resources/js/subscriptions.ts';

test('derives access only from a future server expiry', () => {
    const now = Date.parse('2026-08-29T12:00:00Z');

    assert.equal(isSubscriptionActive('2026-08-29T12:00:01Z', now), true);
    assert.equal(isSubscriptionActive('2026-08-29T12:00:00Z', now), false);
    assert.equal(isSubscriptionActive(null, now), false);
});

test('labels only the purchased package as the current plan', () => {
    const monthly = {kind: 'monthly', productIdentifier: 'monthly-product'} as const;
    const annual = {kind: 'annual', productIdentifier: 'annual-product'} as const;

    assert.equal(subscriptionPackageButtonLabel(monthly, true, 'monthly-product'), 'Current plan active');
    assert.equal(subscriptionPackageButtonLabel(annual, true, 'monthly-product'), 'Manage subscription to change plan');
    assert.equal(subscriptionPackageButtonLabel(annual, false, null), 'Choose annual');
});

test('normalizes localized monthly and annual offering data', () => {
    const packages = normalizeOffering({packages: [
        {
            package_identifier: '$rc_annual',
            product_identifier: 'annual-product',
            localized_price: '£24.99',
            localized_period: '1 year',
            introductory_offer: {
                localized_price: '£0.00',
                localized_period: '7 days',
                period_count: 1,
                payment_mode: 'free_trial',
                is_free_trial: true,
            },
        },
        {
            package_identifier: '$rc_monthly',
            product_identifier: 'monthly-product',
            localized_price: '£4.99',
            localized_period: '1 month',
            introductory_offer: null,
        },
        {package_identifier: '$rc_lifetime'},
    ]});

    assert.deepEqual(packages.map(({kind, localizedPrice}) => ({kind, localizedPrice})), [
        {kind: 'monthly', localizedPrice: '£4.99'},
        {kind: 'annual', localizedPrice: '£24.99'},
    ]);
    assert.equal(packages[0].introductoryOffer, null);
    assert.equal(packages[1].introductoryOffer?.isFreeTrial, true);
    assert.equal(packages[1].introductoryOffer?.localizedPeriod, '7 days');
});

test('keeps cancellation and pending results recoverable', () => {
    assert.deepEqual(nativeError({category: 'cancelled'}, 'Purchase failed.'), {
        category: 'cancelled',
        message: 'Purchase failed.',
    });
    assert.deepEqual(nativeError(JSON.stringify({category: 'pending', message: 'Awaiting payment.'}), 'Purchase failed.'), {
        category: 'pending',
        message: 'Awaiting payment.',
    });
});

test('uses the provider management URL before platform fallbacks', () => {
    assert.equal(managementUrl('ios', 'https://apps.apple.com/account/subscriptions'), 'https://apps.apple.com/account/subscriptions');
    assert.equal(managementUrl('ios'), 'https://apps.apple.com/account/subscriptions');
    assert.equal(managementUrl('android'), 'https://play.google.com/store/account/subscriptions');
    assert.equal(managementUrl('unsupported'), null);
});

test('reports an ordinary browser as unsupported', async () => {
    assert.equal(await subscriptionPlatform(), 'unsupported');
});

test('waits for the matching RevenueCat account switch completion event', async () => {
    const nativeEvents = subscriptionEventHarness();
    const appUserId = 'b00f09bc-1dc3-4aea-a3d8-c8e60acb2773';
    let completed = false;
    const configuration = completeSubscriptionConfiguration(
        appUserId,
        async () => {
            assert.equal(nativeEvents.listenerCount(), 2);

            return {started: true, switching_account: true};
        },
        nativeEvents,
    ).then(() => {
        completed = true;
    });

    await Promise.resolve();
    await Promise.resolve();
    assert.equal(completed, false);

    nativeEvents.emit(subscriptionEvents.configurationCompleted, {app_user_id: 'e7123f23-fd65-41df-8141-d0ce315ec267'});
    await Promise.resolve();
    assert.equal(completed, false);

    nativeEvents.emit(subscriptionEvents.configurationCompleted, {app_user_id: appUserId});
    await configuration;
    assert.equal(completed, true);
    assert.equal(nativeEvents.listenerCount(), 0);
});

test('keeps first-time and same-account RevenueCat configuration synchronous', async () => {
    const nativeEvents = subscriptionEventHarness();

    await completeSubscriptionConfiguration(
        'b00f09bc-1dc3-4aea-a3d8-c8e60acb2773',
        async () => ({configured: true, switching_account: false}),
        nativeEvents,
    );

    assert.equal(nativeEvents.listenerCount(), 0);
});

test('rejects and cleans up when the matching RevenueCat account switch fails', async () => {
    const nativeEvents = subscriptionEventHarness();
    const appUserId = 'b00f09bc-1dc3-4aea-a3d8-c8e60acb2773';
    let configurationStarted!: () => void;
    const started = new Promise<void>((resolve) => {
        configurationStarted = resolve;
    });
    const configuration = completeSubscriptionConfiguration(
        appUserId,
        async () => {
            configurationStarted();

            return {started: true, switching_account: true};
        },
        nativeEvents,
    );

    await started;
    nativeEvents.emit(subscriptionEvents.configurationFailed, {
        app_user_id: appUserId,
        category: 'identity',
        message: 'RevenueCat rejected the account switch.',
    });

    await assert.rejects(configuration, /RevenueCat rejected the account switch\./);
    assert.equal(nativeEvents.listenerCount(), 0);
});

test('serializes RevenueCat account switches so the latest invocation finishes last', async () => {
    const nativeEvents = subscriptionEventHarness();
    const firstAppUserId = 'b00f09bc-1dc3-4aea-a3d8-c8e60acb2773';
    const secondAppUserId = 'e7123f23-fd65-41df-8141-d0ce315ec267';
    const started: string[] = [];
    let firstStarted!: () => void;
    let secondStarted!: () => void;
    const waitForFirst = new Promise<void>((resolve) => {
        firstStarted = resolve;
    });
    const waitForSecond = new Promise<void>((resolve) => {
        secondStarted = resolve;
    });
    const first = completeSubscriptionConfiguration(
        firstAppUserId,
        async () => {
            started.push(firstAppUserId);
            firstStarted();

            return {started: true, switching_account: true};
        },
        nativeEvents,
    );
    const second = completeSubscriptionConfiguration(
        secondAppUserId,
        async () => {
            started.push(secondAppUserId);
            secondStarted();

            return {started: true, switching_account: true};
        },
        nativeEvents,
    );

    await waitForFirst;
    assert.deepEqual(started, [firstAppUserId]);

    nativeEvents.emit(subscriptionEvents.configurationCompleted, {app_user_id: firstAppUserId});
    await first;
    await waitForSecond;
    assert.deepEqual(started, [firstAppUserId, secondAppUserId]);

    nativeEvents.emit(subscriptionEvents.configurationCompleted, {app_user_id: secondAppUserId});
    await second;
    assert.equal(nativeEvents.listenerCount(), 0);
});

test('times out and cleans up when RevenueCat sends no account switch event', async () => {
    const nativeEvents = subscriptionEventHarness();
    let configurationStarted!: () => void;
    const started = new Promise<void>((resolve) => {
        configurationStarted = resolve;
    });
    const configuration = completeSubscriptionConfiguration(
        'b00f09bc-1dc3-4aea-a3d8-c8e60acb2773',
        async () => {
            configurationStarted();

            return {started: true, switching_account: true};
        },
        nativeEvents,
        1,
    );
    const timedOut = assert.rejects(configuration, /timed out while switching accounts/);

    await started;
    await timedOut;
    assert.equal(nativeEvents.listenerCount(), 0);
});

test('removes every registered native listener', () => {
    const source = readFileSync(new URL('../resources/js/subscriptions.ts', import.meta.url), 'utf8');

    assert.match(source, /return \(\) => listeners\.forEach\(\(\{event, listener\}\) => Off\(event, listener\)\)/);
});

test('keeps unlock server-authoritative and renders the required store controls', () => {
    const page = readFileSync(new URL('../resources/js/Pages/Settings/Subscription.vue', import.meta.url), 'utf8');
    const add = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

    assert.match(page, /axios\.post\('\/subscription\/refresh'\)/);
    assert.match(page, /isSubscriptionActive\(account\.value\?\.subscription\?\.expires_at\)/);
    assert.match(page, /platformResolved && platform === 'unsupported'/);
    assert.match(page, /<SettingsPageHeader>Subscription<\/SettingsPageHeader>/);
    assert.match(page, /<h2 class="card-title">Buff\+<\/h2>/);
    assert.match(page, /AI meal analysis and follow-ups/);
    assert.match(page, /No ads/);
    assert.doesNotMatch(page, /Buff\+ inactive/);
    assert.match(page, /Restore purchases/);
    assert.match(page, /Manage subscription/);
    assert.match(page, /https:\/\/usebuff\.app\/privacy\//);
    assert.match(page, /https:\/\/usebuff\.app\/terms\//);
    assert.match(page, /https:\/\/usebuff\.app\/support\//);
    assert.match(add, /subscription_required/);
    assert.match(add, /View Buff\+/);
});

function subscriptionEventHarness() {
    type Listener = (payload: unknown, eventName: string) => void;
    const listeners = new Map<string, Set<Listener>>();

    return {
        On(eventName: string, listener: Listener): void {
            const eventListeners = listeners.get(eventName) ?? new Set<Listener>();

            eventListeners.add(listener);
            listeners.set(eventName, eventListeners);
        },
        Off(eventName: string, listener: Listener): void {
            listeners.get(eventName)?.delete(listener);
        },
        emit(eventName: string, payload: unknown): void {
            listeners.get(eventName)?.forEach((listener) => listener(payload, eventName));
        },
        listenerCount(): number {
            return [...listeners.values()].reduce((count, eventListeners) => count + eventListeners.size, 0);
        },
    };
}
