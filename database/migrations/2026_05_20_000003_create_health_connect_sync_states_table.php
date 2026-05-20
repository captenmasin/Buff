<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_connect_sync_states', function (Blueprint $table): void {
            $table->string('source_type')->primary();
            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('last_successful_sync_at')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('synced_records')->default(0);
            $table->unsignedInteger('deleted_records')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_connect_sync_states');
    }
};
