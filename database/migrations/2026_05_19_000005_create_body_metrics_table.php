<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('body_fat_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_metrics');
    }
};
