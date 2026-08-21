<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_body_metric_photo_uploads', function (Blueprint $table): void {
            $table->json('poses')->nullable()->after('original_names');
        });
    }

    public function down(): void
    {
        Schema::table('pending_body_metric_photo_uploads', function (Blueprint $table): void {
            $table->dropColumn('poses');
        });
    }
};
