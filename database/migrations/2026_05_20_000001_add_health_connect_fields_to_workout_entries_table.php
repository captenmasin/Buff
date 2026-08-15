<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_entries', function (Blueprint $table): void {
            $table->string('source_type')->default('manual')->after('logged_at')->index();
            $table->string('external_id')->nullable()->after('source_type');
            $table->string('external_source')->nullable()->after('external_id');
            $table->string('external_source_package')->nullable()->after('external_source');
            $table->dateTime('started_at')->nullable()->after('external_source_package')->index();
            $table->dateTime('ended_at')->nullable()->after('started_at');
            $table->unsignedInteger('duration_seconds')->nullable()->after('ended_at');
            $table->dateTime('imported_at')->nullable()->after('duration_seconds');
            $table->index(['source_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('workout_entries', function (Blueprint $table): void {
            $table->dropIndex(['source_type', 'external_id']);
            $table->dropColumn([
                'source_type',
                'external_id',
                'external_source',
                'external_source_package',
                'started_at',
                'ended_at',
                'duration_seconds',
                'imported_at',
            ]);
        });
    }
};
