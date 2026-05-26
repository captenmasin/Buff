<?php

it('allows loopback http requests by default', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get('/')
        ->assertOk();
});

it('rejects remote http requests by default', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
        ->get('/')
        ->assertForbidden();
});

it('allows nativephp ios embedded runtime requests by default', function (): void {
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

it('rejects nativephp runtime requests from remote addresses by default', function (): void {
    config(['app.allow_remote_http' => false]);

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.50',
            'NATIVEPHP_RUNNING' => 'true',
            'NATIVEPHP_PLATFORM' => 'ios',
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
