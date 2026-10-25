<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

class RestrictAdminPanelByIp
{
    public function handle(Request $request, Closure $next): mixed
    {
        $allowedIps = config('security.admin_allowed_ips', []);

        if ($allowedIps === []) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if ($clientIp !== null && IpUtils::check($clientIp, $allowedIps)) {
            return $next($request);
        }

        Log::warning('Admin panel request denied by IP allowlist.', [
            'ip' => $clientIp,
            'path' => $request->path(),
        ]);

        abort(403);
    }
}
