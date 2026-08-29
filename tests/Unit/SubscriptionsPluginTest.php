<?php

it('declares a minimal public RevenueCat bridge for both mobile platforms', function (): void {
    $manifest = json_decode(
        file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/nativephp.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $swift = file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/resources/ios/Sources/SubscriptionsFunctions.swift');
    $kotlin = file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/resources/android/src/com/buff/inapppurchases/SubscriptionsFunctions.kt');

    expect(array_column($manifest['bridge_functions'], 'name'))
        ->toBe([
            'Subscriptions.Configure',
            'Subscriptions.LoadOffering',
            'Subscriptions.Purchase',
            'Subscriptions.Restore',
            'Subscriptions.CustomerInfo',
        ])
        ->and($manifest['platforms'])->toBe(['android', 'ios'])
        ->and($manifest['android']['permissions'])->toContain('com.android.vending.BILLING')
        ->and($manifest['android']['dependencies']['implementation'])->toContain('com.revenuecat.purchases:purchases:10.19.1')
        ->and($manifest['ios']['capabilities'])->toContain('in-app-purchase')
        ->and($manifest['ios']['dependencies']['swift_packages'][0]['url'])->toBe('https://github.com/RevenueCat/purchases-ios-spm.git')
        ->and($manifest['events'])->toContain('Buff\\InAppPurchases\\Events\\PurchasePending')
        ->and($swift)->toContain('UUID(uuidString: appUserID)', 'DispatchQueue.main.async', 'PurchasePending')
        ->and($kotlin)->toContain('UUID.fromString(appUserID)', 'Dispatchers.Main', 'PURCHASE_PENDING')
        ->and(strtolower($swift.$kotlin))->not->toContain('receipt', 'secret', 'transactiontoken', 'purchasetoken', 'logout');
});
