<?php

namespace Buff\InAppPurchases\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfferingLoaded
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $packages
     */
    public function __construct(public array $packages = []) {}
}
