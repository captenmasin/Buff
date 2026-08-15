<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $table): void {
            $table->id();
            $table->uuid('device_id')->unique();
            $table->string('account_id')->nullable();
            $table->unsignedBigInteger('cursor')->default(0);
            $table->timestampTz('last_attempted_at', 6)->nullable();
            $table->timestampTz('last_succeeded_at', 6)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
