<?php

it('declares a minimal public RevenueCat bridge for both mobile platforms', function (): void {
    $manifest = json_decode(
        file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/nativephp.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $swift = file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/resources/ios/Sources/SubscriptionsFunctions.swift');
    $kotlin = file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/resources/android/src/com/buff/inapppurchases/SubscriptionsFunctions.kt');
    $configure = collect($manifest['bridge_functions'])->firstWhere('name', 'Subscriptions.Configure');

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
        ->and($configure['android_params'])->toBe(['activity'])
        ->and($manifest['events'])->toContain(
            'Buff\\InAppPurchases\\Events\\ConfigurationCompleted',
            'Buff\\InAppPurchases\\Events\\ConfigurationFailed',
        )
        ->and($manifest['events'])->toContain('Buff\\InAppPurchases\\Events\\PurchasePending')
        ->and($swift)->toContain('UUID(uuidString: appUserID)', 'DispatchQueue.main.async', 'PurchasePending', 'buff_plus')
        ->and($kotlin)->toContain('UUID.fromString(appUserID)', 'Dispatchers.Main', 'PURCHASE_PENDING', 'buff_plus')
        ->and($swift.$kotlin)->not->toContain('ai_meal_analysis')
        ->and(strtolower($swift.$kotlin))->not->toContain('receipt', 'secret', 'transactiontoken', 'purchasetoken', 'logout');
});

it('reports RevenueCat account switch completion and failure instead of reporting early success', function (): void {
    $swift = file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/resources/ios/Sources/SubscriptionsFunctions.swift');
    $kotlin = file_get_contents(__DIR__.'/../../native-plugins/in-app-purchases/resources/android/src/com/buff/inapppurchases/SubscriptionsFunctions.kt');

    expect($swift)
        ->toMatch('/try await Purchases\.shared\.logIn\(appUserID\).*SubscriptionEvent\.configurationCompleted/s')
        ->toMatch('/catch \{.*SubscriptionEvent\.configurationFailed/s')
        ->toContain('return BridgeResponse.success(data: ["started": true, "switching_account": true])')
        ->and($kotlin)
        ->toMatch('/logInWith\(.*onSuccess = \{.*SubscriptionEvent\.CONFIGURATION_COMPLETED/s')
        ->toMatch('/onError = \{ error ->.*SubscriptionEvent\.CONFIGURATION_FAILED/s')
        ->toContain('return BridgeResponse.success(mapOf("started" to true, "switching_account" to true))');
});
