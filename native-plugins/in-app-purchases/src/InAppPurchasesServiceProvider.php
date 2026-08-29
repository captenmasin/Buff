<?php

namespace Buff\InAppPurchases;

use Illuminate\Support\ServiceProvider;

class InAppPurchasesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The mobile bridge is registered from nativephp.json.
    }
}
