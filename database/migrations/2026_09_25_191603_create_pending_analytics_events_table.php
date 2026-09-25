<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_analytics_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('account_id');
            $table->string('name', 100);
            $table->timestampTz('occurred_at', 6);

            $table->index(['account_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_analytics_events');
    }
};
