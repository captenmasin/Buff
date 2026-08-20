<?php

namespace App\Services;

use App\Models\BodyMetric;
use App\Models\PendingBodyMetricPhotoUpload;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BodyMetricPhotoUploader
{
    public function __construct(private readonly BuffApiClient $api) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function upload(BodyMetric $bodyMetric, array $photos): BuffApiResult
    {
        if ($this->metricPendingSync($bodyMetric->id)) {
            $this->stage($bodyMetric, $photos);

            return BuffApiResult::success([
                'pending' => true,
                'message' => 'Photos will upload after sync.',
            ]);
        }

        return $this->api->uploadBodyMetricPhotos($bodyMetric->id, $photos);
    }

    public function flushPending(): void
    {
        PendingBodyMetricPhotoUpload::query()
            ->where('created_at', '<', Date::now()->subDay())
            ->each(fn (PendingBodyMetricPhotoUpload $pending) => $this->discard($pending));

        PendingBodyMetricPhotoUpload::query()->oldest()->each(function (PendingBodyMetricPhotoUpload $pending): void {
            if (SyncState::query()->doesntExist() || $this->metricPendingSync($pending->body_metric_id)) {
                return;
            }

            $photos = $this->filesFromPending($pending);

            if ($photos === []) {
                $this->discard($pending);

                return;
            }

            $result = $this->api->uploadBodyMetricPhotos($pending->body_metric_id, $photos);
            $pending->increment('attempts');

            if ($result->successful()) {
                $this->discard($pending);
            } else {
                $pending->update([
                    'last_error' => $result->message ?? $result->code ?? $result->status->name,
                ]);
            }
        });
    }

    public function discardForMetric(string $bodyMetricId): void
    {
        PendingBodyMetricPhotoUpload::query()
            ->where('body_metric_id', $bodyMetricId)
            ->get()
            ->each(fn (PendingBodyMetricPhotoUpload $pending) => $this->discard($pending));
    }

    public function discardAll(): void
    {
        PendingBodyMetricPhotoUpload::query()
            ->get()
            ->each(fn (PendingBodyMetricPhotoUpload $pending) => $this->discard($pending));
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    private function stage(BodyMetric $bodyMetric, array $photos): void
    {
        $directory = 'progress-photos/'.$bodyMetric->id.'/'.Str::uuid()->toString();
        $paths = [];
        $names = [];

        foreach ($photos as $photo) {
            $filename = Str::uuid()->toString().'.'.$photo->getClientOriginalExtension();
            $path = $directory.'/'.$filename;
            Storage::disk('local')->put($path, $photo->get());
            $paths[] = $path;
            $names[] = $photo->getClientOriginalName();
        }

        PendingBodyMetricPhotoUpload::query()->create([
            'body_metric_id' => $bodyMetric->id,
            'paths' => $paths,
            'original_names' => $names,
        ]);
    }

    private function discard(PendingBodyMetricPhotoUpload $pending): void
    {
        foreach ($pending->paths as $path) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $directory = dirname((string) ($pending->paths[0] ?? ''));

        if ($directory !== '' && $directory !== '.') {
            Storage::disk('local')->deleteDirectory($directory);
        }

        $pending->delete();
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function filesFromPending(PendingBodyMetricPhotoUpload $pending): array
    {
        $photos = [];

        foreach ($pending->paths as $index => $path) {
            if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $absolute = Storage::disk('local')->path($path);
            $name = $pending->original_names[$index] ?? basename($path);
            $photos[] = new UploadedFile($absolute, $name, mime_content_type($absolute) ?: null, null, true);
        }

        return $photos;
    }

    private function metricPendingSync(string $bodyMetricId): bool
    {
        return SyncOutbox::query()
            ->where('record_type', 'body_metrics')
            ->where('record_id', $bodyMetricId)
            ->exists();
    }
}
