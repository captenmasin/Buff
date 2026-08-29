<?php

namespace Buff\InAppPurchases\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfferingFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $category,
        public ?string $message = null,
    ) {}
}
