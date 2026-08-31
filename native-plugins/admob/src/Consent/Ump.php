<?php

declare(strict_types=1);

namespace BlessedZulu\NativePhpAdmob\Consent;

use BlessedZulu\NativePhpAdmob\Contracts\Bridge;
use BlessedZulu\NativePhpAdmob\Events\ConsentChanged;

class Ump
{
    public function __construct(
        protected Bridge $bridge,
        protected bool $enabled = true,
        protected string $debugGeography = 'DISABLED',
    ) {}

    /** @return array{success: bool, data?: mixed, error?: ?string} */
    public function requestConsentInfo(): array
    {
        return $this->call('Admob.UmpRequestInfo', [
            'debug_geography' => $this->debugGeography,
        ]);
    }

    /** @return array{success: bool, data?: mixed, error?: ?string} */
    public function showFormIfRequired(): array
    {
        return $this->call('Admob.UmpShowForm');
    }

    public function canRequestAds(): bool
    {
        $response = $this->call('Admob.UmpCanRequestAds');

        return (bool) ($response['data']['can_request'] ?? false);
    }

    public function status(): string
    {
        $response = $this->call('Admob.UmpStatus');

        return (string) ($response['data']['status'] ?? ConsentChanged::STATUS_UNKNOWN);
    }

    public function privacyOptionsStatus(): string
    {
        $response = $this->call('Admob.UmpPrivacyOptionsStatus');

        return (string) ($response['data']['status'] ?? ConsentChanged::STATUS_UNKNOWN);
    }

    /** @return array{success: bool, data?: mixed, error?: ?string} */
    public function showPrivacyOptionsForm(): array
    {
        return $this->call('Admob.UmpShowPrivacyOptionsForm');
    }

    /** @return array{success: bool, data?: mixed, error?: ?string} */
    private function call(string $method, array $parameters = []): array
    {
        if (! $this->enabled) {
            return ['success' => false, 'data' => null, 'error' => 'ump_disabled'];
        }

        return $this->bridge->call($method, $parameters);
    }
}
