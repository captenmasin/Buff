<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

class BuffCredentialStore
{
    private const CREDENTIAL_PATH = 'buff/credentials.enc';

    private ?string $token = null;

    private ?string $refreshToken = null;

    /** @var array<string, mixed>|null */
    private ?array $account = null;

    private ?Carbon $lastRotationAttemptedAt = null;

    public function __construct()
    {
        $this->restore();
    }

    /** @param array<string, mixed> $account */
    public function store(string $token, array $account, ?string $refreshToken = null): void
    {
        $this->token = $token;
        $this->refreshToken = $refreshToken;
        $this->account = $this->normalizeAccount($account);
        $this->lastRotationAttemptedAt = now();
        $this->persist();
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function refreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /** @return array<string, mixed>|null */
    public function account(): ?array
    {
        return $this->account;
    }

    /** @param array<string, mixed> $account */
    public function updateAccount(array $account): void
    {
        $this->account = $this->normalizeAccount($account);
        $this->persist();
    }

    public function replaceToken(string $token): void
    {
        $this->token = $token;
        $this->lastRotationAttemptedAt = now();
        $this->persist();
    }

    public function rotationIsDue(): bool
    {
        return $this->lastRotationAttemptedAt === null || $this->lastRotationAttemptedAt->lt(now()->subDay());
    }

    public function markRotationAttempted(): void
    {
        $this->lastRotationAttemptedAt = now();
        $this->persist();
    }

    public function clearToken(): void
    {
        $this->token = null;
        $this->lastRotationAttemptedAt = null;
        $this->persist();
    }

    public function clearRefreshToken(): void
    {
        $this->refreshToken = null;
        $this->persist();
    }

    public function clear(): void
    {
        $this->token = null;
        $this->refreshToken = null;
        $this->lastRotationAttemptedAt = null;
        $this->account = null;

        $disk = Storage::disk('local');
        $disk->delete(self::CREDENTIAL_PATH);

        if ($disk->exists(self::CREDENTIAL_PATH)) {
            throw new RuntimeException('Buff credentials could not be removed from this device.');
        }
    }

    /** @param array<string, mixed> $account */
    private function normalizeAccount(array $account): array
    {
        if (is_int($account['id'] ?? null)) {
            $account['id'] = (string) $account['id'];
        }

        return $account;
    }

    private function persist(): void
    {
        if ($this->token === null && $this->account === null) {
            Storage::disk('local')->delete(self::CREDENTIAL_PATH);

            return;
        }

        $payload = json_encode([
            'token' => $this->token,
            'refresh_token' => $this->refreshToken,
            'account' => $this->account,
            'last_rotation_attempted_at' => $this->lastRotationAttemptedAt?->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        if (! Storage::disk('local')->put(self::CREDENTIAL_PATH, Crypt::encryptString($payload))) {
            throw new RuntimeException('Buff credentials could not be saved on this device.');
        }
    }

    private function restore(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::CREDENTIAL_PATH)) {
            return;
        }

        try {
            $encrypted = $disk->get(self::CREDENTIAL_PATH);
            $payload = is_string($encrypted)
                ? json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR)
                : null;
        } catch (DecryptException|JsonException) {
            $payload = null;
        }

        if (! is_array($payload)) {
            $disk->delete(self::CREDENTIAL_PATH);

            return;
        }

        $token = $payload['token'] ?? null;
        $refreshToken = $payload['refresh_token'] ?? null;
        $account = $payload['account'] ?? null;
        $rotationTimestamp = $payload['last_rotation_attempted_at'] ?? null;

        if (($token !== null && (! is_string($token) || $token === ''))
            || ($refreshToken !== null && (! is_string($refreshToken) || $refreshToken === ''))
            || ($account !== null && (! is_array($account) || ! is_string($account['id'] ?? null)))
            || ($token !== null && $account === null)
            || ($refreshToken !== null && $account === null)
            || ($rotationTimestamp !== null && ! is_int($rotationTimestamp))) {
            $disk->delete(self::CREDENTIAL_PATH);

            return;
        }

        $this->token = $token;
        $this->refreshToken = $refreshToken;
        $this->account = $account;
        $this->lastRotationAttemptedAt = is_int($rotationTimestamp)
            ? Carbon::createFromTimestamp($rotationTimestamp)
            : null;
    }
}
