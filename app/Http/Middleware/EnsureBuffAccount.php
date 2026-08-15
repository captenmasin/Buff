<?php

namespace App\Http\Middleware;

use App\Services\BuffCredentialStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuffAccount
{
    public function __construct(private readonly BuffCredentialStore $credentials) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->credentials->token() !== null) {
            return $next($request);
        }

        return redirect()->route('account.login');
    }
}
