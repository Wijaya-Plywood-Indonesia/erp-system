<?php

namespace App\Http\Middleware;

use App\Filament\Pages\PortalWahana;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToPortalForAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (
            $user?->hasAnyRole(['super_admin', 'portal admin'])
            && $request->routeIs('filament.admin.pages.dashboard')
        ) {
            return redirect(PortalWahana::getUrl());
        }

        return $next($request);
    }
}