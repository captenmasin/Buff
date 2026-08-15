<?php

namespace Tests;

use App\Http\Middleware\EnsureBuffAccount;
use App\Models\SyncState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureBuffAccount::class);
        $this->withoutVite();
        Storage::fake('local');
        SyncState::current();
    }
}
