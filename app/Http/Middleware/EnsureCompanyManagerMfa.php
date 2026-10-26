<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Illuminate\Http\Request;

class EnsureCompanyManagerMfa
{
    public function handle(Request $request, Closure $next): mixed
    {
        // The setup page must remain reachable before MFA has been configured.
        if ($request->routeIs('filament.admin.auth.multi-factor-authentication.set-up-required')) {
            return $next($request);
        }

        $user = Filament::auth()->user();

        if (! $user?->is_company_manager) {
            return $next($request);
        }

        if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
