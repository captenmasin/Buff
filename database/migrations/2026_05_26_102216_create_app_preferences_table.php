<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('weight_unit', 2)->default('kg');
            $table->string('height_unit', 2)->default('cm');
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_preferences');
    }
};
