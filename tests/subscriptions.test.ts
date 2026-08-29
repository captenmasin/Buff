import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {
    isSubscriptionActive,
    managementUrl,
    nativeError,
    normalizeOffering,
    subscriptionPlatform,
} from '../resources/js/subscriptions.ts';

test('derives access only from a future server expiry', () => {
    const now = Date.parse('2026-08-29T12:00:00Z');

    assert.equal(isSubscriptionActive('2026-08-29T12:00:01Z', now), true);
    assert.equal(isSubscriptionActive('2026-08-29T12:00:00Z', now), false);
    assert.equal(isSubscriptionActive(null, now), false);
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

test('removes every registered native listener', () => {
    const source = readFileSync(new URL('../resources/js/subscriptions.ts', import.meta.url), 'utf8');

    assert.match(source, /return \(\) => listeners\.forEach\(\(\{event, listener\}\) => Off\(event, listener\)\)/);
});

test('keeps unlock server-authoritative and renders the required store controls', () => {
    const page = readFileSync(new URL('../resources/js/Pages/Settings/Subscription.vue', import.meta.url), 'utf8');
    const add = readFileSync(new URL('../resources/js/Pages/Add.vue', import.meta.url), 'utf8');

    assert.match(page, /axios\.post\('\/subscription\/refresh'\)/);
    assert.match(page, /isSubscriptionActive\(account\.value\?\.subscription\?\.expires_at\)/);
    assert.match(page, /Restore purchases/);
    assert.match(page, /Manage subscription/);
    assert.match(page, /https:\/\/usebuff\.app\/privacy\//);
    assert.match(page, /https:\/\/usebuff\.app\/terms\//);
    assert.match(page, /https:\/\/usebuff\.app\/support\//);
    assert.match(add, /subscription_required/);
    assert.match(add, /View Buff\+/);
});
