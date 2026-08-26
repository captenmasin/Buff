<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_connect_ignored_workouts', function (Blueprint $table): void {
            $table->string('source_type')->default('health_connect')->after('id');
            $table->dropIndex(['external_id']);
            $table->unique(['source_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('health_connect_ignored_workouts', function (Blueprint $table): void {
            $table->dropUnique(['source_type', 'external_id']);
            $table->index('external_id');
            $table->dropColumn('source_type');
        });
    }
};
