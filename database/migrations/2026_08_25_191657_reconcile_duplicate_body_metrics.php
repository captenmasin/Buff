<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('body_metrics')
            ->orderBy('date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'date'])
            ->groupBy('date')
            ->each(function ($metrics): void {
                $canonicalId = $metrics->first()->id;
                $duplicateIds = $metrics->skip(1)->pluck('id');

                if ($duplicateIds->isEmpty()) {
                    return;
                }

                DB::table('pending_body_metric_photo_uploads')
                    ->whereIn('body_metric_id', $duplicateIds)
                    ->update(['body_metric_id' => $canonicalId]);
                DB::table('sync_outboxes')
                    ->where('record_type', 'body_metrics')
                    ->whereIn('record_id', $duplicateIds)
                    ->delete();
                DB::table('body_metrics')->whereIn('id', $duplicateIds)->delete();
            });
    }

    public function down(): void {}
};
