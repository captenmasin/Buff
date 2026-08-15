<?php

namespace App\Services;

use App\BuffApiStatus;

class BuffApiResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public readonly BuffApiStatus $status,
        public readonly array $data = [],
        public readonly array $errors = [],
        public readonly ?string $message = null,
        public readonly ?string $code = null,
        public readonly ?int $httpStatus = null,
    ) {}

    public function successful(): bool
    {
        return $this->status === BuffApiStatus::Success;
    }

    /** @param array<string, mixed> $data */
    public static function success(array $data = []): self
    {
        return new self(BuffApiStatus::Success, $data);
    }
}
