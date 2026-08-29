<?php

namespace Buff\InAppPurchases\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RestoreCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public bool $entitled) {}
}
