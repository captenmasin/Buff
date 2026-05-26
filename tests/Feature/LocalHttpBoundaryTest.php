<?php

it('allows loopback http requests without a local http boundary', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get('/')
        ->assertOk();
});

it('allows remote http requests without a local http boundary', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->get('/')
        ->assertOk();
});

it('allows nativephp android embedded runtime requests without a local http boundary', function (): void {
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

it('allows nativephp runtime requests from remote addresses without a local http boundary', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.50',
            'NATIVEPHP_RUNNING' => 'true',
            'NATIVEPHP_PLATFORM' => 'android',
        ])
        ->get('/')
        ->assertOk();
});

it('allows remote http requests when explicitly enabled', function (): void {
    config(['app.allow_remote_http' => true]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->get('/')
        ->assertOk();
});
