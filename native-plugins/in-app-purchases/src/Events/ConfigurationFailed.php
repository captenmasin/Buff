<?php

namespace Buff\InAppPurchases\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConfigurationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $app_user_id,
        public string $category,
        public ?string $message = null,
    ) {}
}
