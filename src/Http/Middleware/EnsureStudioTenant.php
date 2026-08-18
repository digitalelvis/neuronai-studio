<?php

namespace DigitalElvis\NeuronAIStudio\Http\Middleware;

use DigitalElvis\NeuronAIStudio\Tenancy\StudioTenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudioTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! StudioTenancy::enabled()) {
            return $next($request);
        }

        if (StudioTenancy::id() === null) {
            abort(403, 'Tenant context required.');
        }

        return $next($request);
    }
}
