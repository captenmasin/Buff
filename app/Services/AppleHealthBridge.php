<?php

namespace App\Services;

use App\Models\HealthConnectSyncState;
use Closure;
use Native\Mobile\Facades\System;
use Throwable;

class AppleHealthBridge
{
    public function __construct(
        private ?Closure $nativeCaller = null,
        private ?Closure $iosDetector = null,
    ) {}

    public function sharedStatus(): array
    {
        $state = HealthConnectSyncState::query()->find(HealthConnectSyncState::APPLE_HEALTH_SOURCE_TYPE);
        $isIos = $this->isIos();

        return [
            'is_ios' => $isIos,
            'supported' => $isIos,
            'available' => null,
            'has_permissions' => null,
            'foreground_granted' => null,
            'background_granted' => null,
            'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
            'last_successful_sync_at' => $state?->last_successful_sync_at?->toIso8601String(),
            'last_status' => $state?->last_status,
            'last_error' => $state?->last_error,
            'synced_records' => $state?->synced_records ?? 0,
            'deleted_records' => $state?->deleted_records ?? 0,
        ];
    }

    public function call(string $method, array $params = []): array
    {
        if (! $this->isIos()) {
            return [
                'is_ios' => false,
                'supported' => false,
                'available' => false,
                'status' => 'unsupported',
                'message' => 'Apple Health is only available in the iOS app.',
            ];
        }

        try {
            $payload = json_encode($params, JSON_THROW_ON_ERROR);
            $result = $this->nativeCaller
                ? ($this->nativeCaller)($method, $payload)
                : nativephp_call($method, $payload);
        } catch (Throwable) {
            return $this->errorPayload('Apple Health bridge call failed.');
        }

        if (! $result) {
            return [
                'supported' => true,
                'available' => false,
                'status' => 'unavailable',
                'message' => 'Apple Health bridge is not registered.',
            ];
        }

        $decoded = json_decode($result, true);

        if (! is_array($decoded)) {
            return $this->errorPayload('Apple Health returned an invalid response.');
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }

        if (isset($decoded['status']) && ! is_string($decoded['status'])) {
            return $this->errorPayload('Apple Health returned a malformed status.');
        }

        return [
            'is_ios' => true,
            'supported' => true,
            ...$decoded,
        ];
    }

    private function isIos(): bool
    {
        if ($this->iosDetector) {
            return (bool) ($this->iosDetector)();
        }

        if ($this->nativeCaller) {
            return true;
        }

        if (! function_exists('nativephp_call')) {
            return false;
        }

        if (request()->server('NATIVEPHP_PLATFORM') === 'ios' || getenv('NATIVEPHP_PLATFORM') === 'ios') {
            return true;
        }

        if (function_exists('nativephp_can') && nativephp_can('AppleHealth.Status')) {
            return true;
        }

        try {
            return System::isIos();
        } catch (Throwable) {
            return false;
        }
    }

    private function errorPayload(string $message): array
    {
        return [
            'supported' => true,
            'available' => false,
            'status' => 'error',
            'message' => $message,
        ];
    }
}
