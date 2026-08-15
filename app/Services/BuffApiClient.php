<?php

namespace App\Services;

use App\BuffApiStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class BuffApiClient
{
    public function __construct(private readonly BuffCredentialStore $credentials) {}

    /** @param array<string, mixed> $data */
    public function get(string $path, array $data = []): BuffApiResult
    {
        return $this->send('get', $path, $data);
    }

    /** @param array<string, mixed> $data */
    public function post(string $path, array $data = [], bool $authenticated = true): BuffApiResult
    {
        return $this->send('post', $path, $data, $authenticated);
    }

    /** @param array<string, mixed> $data */
    public function patch(string $path, array $data): BuffApiResult
    {
        return $this->send('patch', $path, $data);
    }

    /** @param array<string, mixed> $data */
    public function delete(string $path, array $data = []): BuffApiResult
    {
        return $this->send('delete', $path, $data);
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function analyzeMeal(array $photos, ?string $note): BuffApiResult
    {
        $request = $this->request(config('buff.http.meal_analysis_timeout'));

        if ($request instanceof BuffApiResult) {
            return $request;
        }

        foreach ($photos as $photo) {
            $request = $request->attach(
                'photos[]',
                $photo->get(),
                $photo->getClientOriginalName(),
                ['Content-Type' => $photo->getMimeType() ?? 'application/octet-stream'],
            );
        }

        try {
            return $this->result($request->post('meal-analyses', ['note' => $note]));
        } catch (ConnectionException) {
            return new BuffApiResult(
                BuffApiStatus::ConnectionFailed,
                message: 'Could not connect to Buff.',
            );
        }
    }

    /** @param array<string, mixed> $data */
    private function send(string $method, string $path, array $data, bool $authenticated = true): BuffApiResult
    {
        $request = $this->request(authenticated: $authenticated);

        if ($request instanceof BuffApiResult) {
            return $request;
        }

        try {
            $response = match ($method) {
                'get' => $request->get($path, $data),
                'post' => $request->post($path, $data),
                'patch' => $request->patch($path, $data),
                'delete' => $request->delete($path, $data),
            };

            return $this->result($response);
        } catch (ConnectionException) {
            return new BuffApiResult(
                BuffApiStatus::ConnectionFailed,
                message: 'Could not connect to Buff.',
            );
        }
    }

    private function request(?int $timeout = null, bool $authenticated = true): PendingRequest|BuffApiResult
    {
        $request = Http::baseUrl(rtrim((string) config('buff.api_url'), '/'))
            ->acceptJson()
            ->connectTimeout(config('buff.http.connect_timeout'))
            ->timeout($timeout ?? config('buff.http.timeout'));

        if (! $authenticated) {
            return $request;
        }

        $token = $this->credentials->token();

        return $token
            ? $request->withToken($token)
            : new BuffApiResult(BuffApiStatus::Unauthenticated, message: 'Sign in to sync Buff.');
    }

    private function result(Response $response): BuffApiResult
    {
        $data = $response->json();
        $data = is_array($data) ? $data : [];
        $code = is_string($data['code'] ?? null) ? $data['code'] : null;
        $message = is_string($data['message'] ?? null) ? $data['message'] : null;
        $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];

        $status = match (true) {
            $response->successful() => BuffApiStatus::Success,
            $response->status() === 401 => BuffApiStatus::Unauthenticated,
            $response->status() === 403 && $code === 'email_not_verified' => BuffApiStatus::EmailNotVerified,
            $response->status() === 403 => BuffApiStatus::Forbidden,
            $response->status() === 422 => BuffApiStatus::ValidationFailed,
            $response->status() === 429 => BuffApiStatus::RateLimited,
            default => BuffApiStatus::Failed,
        };

        return new BuffApiResult($status, $data, $errors, $message, $code, $response->status());
    }
}
