<?php

use BlessedZulu\NativePhpAdmob\Admob;
use BlessedZulu\NativePhpAdmob\Consent\Ump;
use BlessedZulu\NativePhpAdmob\Support\FakeBridge;
use BlessedZulu\NativePhpAdmob\Support\SlotResolver;
use BlessedZulu\NativePhpAdmob\Support\TestAdUnits;

it('declares manual startup and privacy options without auto init', function (): void {
    $manifest = json_decode(
        file_get_contents(__DIR__.'/../nativephp.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $package = json_decode(
        file_get_contents(__DIR__.'/../composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $androidManifest = file_get_contents(__DIR__.'/../resources/android/AndroidManifest.xml');
    $functions = array_column($manifest['bridge_functions'], 'name');

    expect($package['require']['nativephp/mobile'])->toBe('^4.3')
        ->and($package['require']['illuminate/contracts'])->toBe('^13.0')
        ->and($functions)->toContain(
            'Admob.ConfigurePolicy',
            'Admob.Initialize',
            'Admob.UmpPrivacyOptionsStatus',
            'Admob.UmpShowPrivacyOptionsForm',
        )
        ->and($manifest['android'])->not->toHaveKey('init_function')
        ->and($manifest['ios'])->not->toHaveKey('init_function')
        ->and($functions)->not->toContain(
            'Admob.LoadInterstitial',
            'Admob.LoadRewarded',
            'Admob.LoadRewardedInterstitial',
            'Admob.LoadAppOpen',
        )
        ->and($androidManifest)->toContain(
            'com.google.android.gms.ads.MobileAdsInitProvider',
            'tools:node="remove"',
        );
});

it('keeps startup disabled while mapping only the approved privacy policy payload', function (): void {
    $disabledBridge = new FakeBridge;
    $disabled = new Admob($disabledBridge, [
        'enabled' => false,
        'test_mode' => true,
        'slots' => ['banner' => []],
    ]);

    $disabled->configurePolicy(true, true, 'T');
    $disabled->initialize();

    expect($disabledBridge->calls)->toBe([[
        'method' => 'Admob.ConfigurePolicy',
        'params' => [
            'under_age_of_consent' => true,
            'non_personalized' => true,
            'max_content_rating' => 'T',
        ],
    ]]);

    $bridge = new FakeBridge;
    $admob = new Admob($bridge, ['enabled' => true]);
    $admob->configurePolicy(true, true, 'T');

    expect($bridge->calls)->toBe([[
        'method' => 'Admob.ConfigurePolicy',
        'params' => [
            'under_age_of_consent' => true,
            'non_personalized' => true,
            'max_content_rating' => 'T',
        ],
    ]]);
});

it('reports privacy options explicitly and keeps demo banners account-safe', function (): void {
    $bridge = (new FakeBridge)
        ->stub('Admob.UmpPrivacyOptionsStatus', [
            'success' => true,
            'data' => ['status' => 'required'],
        ]);
    $ump = new Ump($bridge, debugGeography: 'EEA');
    $resolver = new SlotResolver(['test_mode' => true]);

    expect($ump->privacyOptionsStatus())->toBe('required')
        ->and($ump->showPrivacyOptionsForm()['success'])->toBeTrue()
        ->and($resolver->resolve('banner', 'app_shell'))->toBe(TestAdUnits::BANNER);

    $ump->requestConsentInfo();

    expect($bridge->calls)->toContain([
        'method' => 'Admob.UmpRequestInfo',
        'params' => ['debug_geography' => 'EEA'],
    ]);
});

it('centralizes non-personalized requests and emits banner height on both platforms', function (): void {
    $android = file_get_contents(__DIR__.'/../resources/android/src/AdmobInit.kt')
        .file_get_contents(__DIR__.'/../resources/android/src/AdmobFunctions.kt');
    $ios = file_get_contents(__DIR__.'/../resources/ios/Sources/AdmobInit.swift')
        .file_get_contents(__DIR__.'/../resources/ios/Sources/AdmobFunctions.swift');

    expect($android)->toContain(
        'fun bannerRequest(): AdRequest',
        'putString("npa", "1")',
        '"height" to (newAdView.adSize?.height ?: 0)',
        'privacyOptionsStatusString',
    )->and(substr_count($android, 'setAdSize(adaptiveBannerSize(activity))'))->toBe(1)
        ->and($ios)->toContain(
            'static func bannerRequest() -> Request',
            'extras.additionalParameters = ["npa": "1"]',
            '"height": Int(bannerView.adSize.size.height.rounded(.up))',
            'presentPrivacyOptionsForm',
        );
});
