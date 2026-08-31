<?php

declare(strict_types=1);

namespace BlessedZulu\NativePhpAdmob\Consent;

use BlessedZulu\NativePhpAdmob\Contracts\Bridge;

class Att
{
    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_DENIED = 'denied';

    public const STATUS_RESTRICTED = 'restricted';

    public const STATUS_NOT_DETERMINED = 'notDetermined';

    public const STATUS_UNSUPPORTED = 'unsupported';

    public function __construct(
        protected Bridge $bridge,
        protected bool $enabled = true,
    ) {}

    /** @return array{success: bool, data?: mixed, error?: ?string} */
    public function requestAuthorization(): array
    {
        if (! $this->isSupported()) {
            return ['success' => false, 'data' => null, 'error' => 'att_unsupported'];
        }

        return $this->bridge->call('Admob.AttRequest');
    }

    public function status(): string
    {
        if (! $this->isSupported()) {
            return self::STATUS_UNSUPPORTED;
        }

        $response = $this->bridge->call('Admob.AttStatus');

        return (string) ($response['data']['status'] ?? self::STATUS_NOT_DETERMINED);
    }

    protected function isSupported(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $response = $this->bridge->call('Admob.Platform');

        return (string) ($response['data']['platform'] ?? '') === 'ios';
    }
}
