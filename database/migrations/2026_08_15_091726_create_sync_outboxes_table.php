<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->string('record_type');
            $table->uuid('record_id');
            $table->json('payload')->nullable();
            $table->timestampTz('client_updated_at', 6);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps(6);

            $table->unique(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_outboxes');
    }
};
