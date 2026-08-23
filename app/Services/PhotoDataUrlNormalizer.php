<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PhotoDataUrlNormalizer
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<int, resource> */
    private array $temporaryFiles = [];

    public function normalize(Request $request): void
    {
        if ($request->files->has('photos')) {
            return;
        }

        $photos = $request->input('photos');

        if (! is_array($photos)) {
            return;
        }

        $uploads = [];

        foreach (array_values($photos) as $index => $photo) {
            $uploads[] = $this->upload($photo, $index);
        }

        $request->request->remove('photos');
        $request->files->set('photos', $uploads);
    }

    private function upload(mixed $photo, int $index): UploadedFile
    {
        if (! is_string($photo) || ! preg_match('/\Adata:(image\/(?:jpeg|png|webp));base64,([A-Za-z0-9+\/=]+)\z/', $photo, $matches)) {
            $this->invalid($index);
        }

        $encoded = $matches[2];

        if (strlen($encoded) > 4 * (int) ceil(self::MAX_BYTES / 3)) {
            $this->invalid($index);
        }

        $contents = base64_decode($encoded, true);

        if ($contents === false || strlen($contents) > self::MAX_BYTES) {
            $this->invalid($index);
        }

        $temporaryFile = tmpfile();

        if ($temporaryFile === false || fwrite($temporaryFile, $contents) !== strlen($contents)) {
            throw new RuntimeException('Could not prepare the uploaded photo.');
        }

        $metadata = stream_get_meta_data($temporaryFile);
        $this->temporaryFiles[] = $temporaryFile;
        $extension = $matches[1] === 'image/jpeg' ? 'jpg' : substr($matches[1], 6);

        return new UploadedFile(
            $metadata['uri'],
            Str::uuid().'.'.$extension,
            $matches[1],
            UPLOAD_ERR_OK,
            true,
        );
    }

    private function invalid(int $index): never
    {
        throw ValidationException::withMessages([
            "photos.{$index}" => ['The photo must be a valid image no larger than 5 MB.'],
        ]);
    }
}
