<?php

namespace App\Services;

use App\BodyMetricPhotoPose;
use App\Models\BodyMetric;
use App\Models\PendingBodyMetricPhotoUpload;
use App\Models\SyncOutbox;
use App\Models\SyncState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BodyMetricPhotoUploader
{
    public function __construct(private readonly BuffApiClient $api) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     * @param  array<int, string>  $poses
     */
    public function upload(BodyMetric $bodyMetric, array $photos, array $poses): BuffApiResult
    {
        if ($this->metricPendingSync($bodyMetric->id)) {
            $this->stage($bodyMetric, $photos, $poses);

            return BuffApiResult::success([
                'pending' => true,
                'message' => 'Photos will upload after sync.',
            ]);
        }

        return $this->api->uploadBodyMetricPhotos($bodyMetric->id, $photos, $poses);
    }

    public function flushPending(): void
    {
        PendingBodyMetricPhotoUpload::query()->oldest()->each(function (PendingBodyMetricPhotoUpload $pending): void {
            if (SyncState::query()->doesntExist() || $this->metricPendingSync($pending->body_metric_id)) {
                return;
            }

            [$photos, $poses] = $this->filesFromPending($pending);

            if ($photos === []) {
                $this->discard($pending);

                return;
            }

            $result = $this->api->uploadBodyMetricPhotos($pending->body_metric_id, $photos, $poses);
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

    /**
     * @return array<int, array{id: string, url: string, mime_type: string, pose: string|null, pending: bool}>
     */
    public function pendingPhotosFor(BodyMetric $bodyMetric): array
    {
        $photos = [];

        PendingBodyMetricPhotoUpload::query()
            ->where('body_metric_id', $bodyMetric->id)
            ->oldest()
            ->each(function (PendingBodyMetricPhotoUpload $pending) use (&$photos, $bodyMetric): void {
                foreach ($pending->paths as $index => $path) {
                    if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
                        continue;
                    }

                    $absolute = Storage::disk('local')->path($path);
                    $pose = is_array($pending->poses) ? ($pending->poses[$index] ?? null) : null;

                    $photos[] = [
                        'id' => $pending->id.':'.$index,
                        'url' => url("/progress/body-metrics/{$bodyMetric->id}/photos/pending/{$pending->id}/{$index}"),
                        'mime_type' => mime_content_type($absolute) ?: 'image/jpeg',
                        'pose' => is_string($pose) ? $pose : null,
                        'pending' => true,
                    ];
                }
            });

        usort($photos, fn (array $left, array $right): int => BodyMetricPhotoPose::sortKey($left['pose']) <=> BodyMetricPhotoPose::sortKey($right['pose']));

        return array_slice($photos, 0, 3);
    }

    public function pendingPhotoResponse(BodyMetric $bodyMetric, string $pendingId, int $index): StreamedResponse
    {
        $pending = PendingBodyMetricPhotoUpload::query()
            ->where('body_metric_id', $bodyMetric->id)
            ->whereKey($pendingId)
            ->firstOrFail();

        $path = $pending->paths[$index] ?? null;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
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
     * @param  array<int, string>  $poses
     */
    private function stage(BodyMetric $bodyMetric, array $photos, array $poses): void
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
            'poses' => array_values($poses),
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
     * @return array{0: array<int, UploadedFile>, 1: array<int, string>}
     */
    private function filesFromPending(PendingBodyMetricPhotoUpload $pending): array
    {
        $photos = [];
        $poses = [];

        foreach ($pending->paths as $index => $path) {
            if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $absolute = Storage::disk('local')->path($path);
            $name = $pending->original_names[$index] ?? basename($path);
            $fallback = BodyMetricPhotoPose::cases()[$index] ?? BodyMetricPhotoPose::Front;
            $pose = is_array($pending->poses) ? ($pending->poses[$index] ?? null) : null;
            $photos[] = new UploadedFile($absolute, $name, mime_content_type($absolute) ?: null, null, true);
            $poses[] = is_string($pose) ? $pose : $fallback->value;
        }

        return [$photos, $poses];
    }

    private function metricPendingSync(string $bodyMetricId): bool
    {
        return SyncOutbox::query()
            ->where('record_type', 'body_metrics')
            ->where('record_id', $bodyMetricId)
            ->exists();
    }
}
