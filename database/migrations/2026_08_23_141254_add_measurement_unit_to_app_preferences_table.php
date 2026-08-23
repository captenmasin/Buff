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
        Schema::table('app_preferences', function (Blueprint $table): void {
            $table->string('measurement_unit', 2)->default('cm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_preferences', function (Blueprint $table): void {
            $table->dropColumn('measurement_unit');
        });
    }
};
