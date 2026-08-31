<?php

it('registers the pinned manual AdMob banner bridge once', function (): void {
    $root = __DIR__.'/../../';
    $manifest = json_decode(
        file_get_contents($root.'native-plugins/admob/nativephp.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $package = json_decode(
        file_get_contents($root.'native-plugins/admob/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $provider = file_get_contents($root.'app/Providers/NativeServiceProvider.php');
    $androidManifest = file_get_contents($root.'native-plugins/admob/resources/android/AndroidManifest.xml');
    $names = array_column($manifest['bridge_functions'], 'name');

    expect($package['name'])->toBe('buff/admob')
        ->and($package['version'])->toBe('1.0.0')
        ->and($package['require']['nativephp/mobile'])->toBe('^4.3')
        ->and($names)->toContain(
            'Admob.ConfigurePolicy',
            'Admob.Initialize',
            'Admob.UmpPrivacyOptionsStatus',
            'Admob.UmpShowPrivacyOptionsForm',
        )
        ->and($manifest['android'])->not->toHaveKey('init_function')
        ->and($manifest['ios'])->not->toHaveKey('init_function')
        ->and($androidManifest)->toContain('tools:node="remove"')
        ->and(substr_count($provider, 'AdmobServiceProvider::class'))->toBe(1);
});

it('keeps native banner requests policy-gated and non-personalized when required', function (): void {
    $root = __DIR__.'/../../native-plugins/admob/resources/';
    $kotlin = file_get_contents($root.'android/src/AdmobInit.kt')
        .file_get_contents($root.'android/src/AdmobFunctions.kt');
    $swift = file_get_contents($root.'ios/Sources/AdmobInit.swift')
        .file_get_contents($root.'ios/Sources/AdmobFunctions.swift');

    expect($kotlin)->toContain(
        'TAG_FOR_CHILD_DIRECTED_TREATMENT_FALSE',
        'TAG_FOR_UNDER_AGE_OF_CONSENT_TRUE',
        'putString("npa", "1")',
        'AdmobInit.canLoadBanner(activity)',
        '"height" to (newAdView.adSize?.height ?: 0)',
    )->and($swift)->toContain(
        'tagForChildDirectedTreatment = false',
        'tagForUnderAgeOfConsent = NSNumber(value: underAgeOfConsent)',
        'extras.additionalParameters = ["npa": "1"]',
        'AdmobInit.canLoadBanner()',
        '"height": Int(bannerView.adSize.size.height.rounded(.up))',
    );
});

it('adds the device calibration to the measured bottom navigation offset', function (): void {
    $banner = file_get_contents(__DIR__.'/../../native-plugins/admob/src/Builders/BannerAd.php');

    expect($banner)->toContain(
        '$calibration = $offset === 0 ? 0 : (int) config(',
        '$offset = max(0, ($offset ?? 0) + $calibration);',
    );
});
