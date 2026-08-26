<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('body_metrics', function (Blueprint $table): void {
            $table->dropIndex(['date']);
            $table->unique('date');
        });
    }

    public function down(): void
    {
        Schema::table('body_metrics', function (Blueprint $table): void {
            $table->dropUnique(['date']);
            $table->index('date');
        });
    }
};
