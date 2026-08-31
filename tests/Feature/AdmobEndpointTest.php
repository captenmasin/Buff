<?php

use BlessedZulu\NativePhpAdmob\Contracts\Bridge;
use BlessedZulu\NativePhpAdmob\Support\FakeBridge;

it('keeps ads disabled while allowing privacy policy setup', function (): void {
    config()->set('admob.enabled', false);
    $bridge = new FakeBridge;
    $this->app->instance(Bridge::class, $bridge);
    $this->app->forgetInstance('admob');

    $this->postJson('/_admob/call', [
        'kind' => 'lifecycle',
        'action' => 'enabled',
    ])->assertOk()
        ->assertJsonPath('enabled', false);

    $this->postJson('/_admob/call', [
        'kind' => 'lifecycle',
        'action' => 'configurePolicy',
        'under_age_of_consent' => true,
        'non_personalized' => true,
        'max_content_rating' => 'T',
    ])->assertOk()
        ->assertJsonPath('ok', true);

    $this->postJson('/_admob/call', [
        'kind' => 'lifecycle',
        'action' => 'initialize',
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'admob_disabled');

    $this->postJson('/_admob/call', [
        'kind' => 'ad',
        'format' => 'interstitial',
        'slot' => 'unused',
        'action' => 'load',
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'invalid_ad_request');

    $this->postJson('/_admob/call', [
        'kind' => 'ad',
        'format' => 'banner',
        'slot' => 'another_slot',
        'action' => 'load',
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'invalid_ad_request');

    expect($bridge->calls)->toBe([[
        'method' => 'Admob.ConfigurePolicy',
        'params' => [
            'under_age_of_consent' => true,
            'non_personalized' => true,
            'max_content_rating' => 'T',
        ],
    ]]);
});

it('leaves banner consent enforcement to the native bridge', function (): void {
    config()->set('admob.enabled', true);
    config()->set('admob.test_mode', true);
    $bridge = new FakeBridge;
    $this->app->instance(Bridge::class, $bridge);
    $this->app->forgetInstance('admob');

    $this->postJson('/_admob/call', [
        'kind' => 'ad',
        'format' => 'banner',
        'slot' => 'app_shell',
        'action' => 'show',
        'position' => 'bottom',
        'offset' => 0,
    ])->assertOk();

    expect($bridge->calls)->toContain([
        'method' => 'Admob.ShowBanner',
        'params' => [
            'slot' => 'app_shell',
            'format' => 'banner',
            'unit_id' => 'ca-app-pub-3940256099942544/6300978111',
            'position' => 'bottom',
            'offset' => 0,
            'safe_area' => true,
        ],
    ]);
});
