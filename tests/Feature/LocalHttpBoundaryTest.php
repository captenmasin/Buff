<?php

use App\Models\DailyGoal;

beforeEach(function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);
});

it('allows loopback http requests', function (string $address): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => $address])
        ->get('/')
        ->assertOk();
})->with([
    'IPv4' => '127.0.0.1',
    'IPv4 loopback subnet' => '127.255.255.255',
    'compressed IPv6' => '::1',
    'expanded IPv6' => '0:0:0:0:0:0:0:1',
]);

it('rejects remote http requests by default', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->get('/')
        ->assertForbidden();
});

it('allows nativephp android embedded loopback requests', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '0.0.0.0',
            'NATIVEPHP_RUNNING' => 'true',
            'NATIVEPHP_PLATFORM' => 'android',
        ])
        ->get('/')
        ->assertOk();
});

it('allows nativephp ios embedded loopback requests', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '0.0.0.0',
            'NATIVEPHP_RUNNING' => 'true',
            'NATIVEPHP_PLATFORM' => 'ios',
        ])
        ->get('/')
        ->assertOk();
});

it('rejects nativephp runtime claims from remote addresses', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.50',
            'NATIVEPHP_RUNNING' => 'true',
            'NATIVEPHP_PLATFORM' => 'android',
        ])
        ->get('/')
        ->assertForbidden();
});

it('allows remote http requests when explicitly enabled', function (): void {
    config(['app.allow_remote_http' => true]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->get('/')
        ->assertOk();
});
