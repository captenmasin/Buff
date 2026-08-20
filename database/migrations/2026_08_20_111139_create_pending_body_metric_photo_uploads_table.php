<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_body_metric_photo_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('body_metric_id')->index();
            $table->json('paths');
            $table->json('original_names');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_body_metric_photo_uploads');
    }
};
