<?php

namespace Buff\InAppPurchases\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $package_identifier,
        public string $product_identifier,
        public bool $entitled,
    ) {}
}
