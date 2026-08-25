<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.allow_remote_http')) {
            return $next($request);
        }

        if ($this->isLoopbackAddress($request->ip())) {
            return $next($request);
        }

        if ($this->isNativeRuntimeRequest($request)) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN, 'Buff only accepts local HTTP requests by default.');
    }

    private function isLoopbackAddress(?string $ip): bool
    {
        return IpUtils::checkIp($ip ?? '', ['127.0.0.0/8', '::1']);
    }

    private function isNativeRuntimeRequest(Request $request): bool
    {
        if ($request->server('NATIVEPHP_RUNNING') !== 'true') {
            return false;
        }

        if (! in_array($request->server('NATIVEPHP_PLATFORM'), ['android', 'ios'], true)) {
            return false;
        }

        return $this->isLoopbackAddress($request->ip()) || $request->ip() === '0.0.0.0';
    }
}
