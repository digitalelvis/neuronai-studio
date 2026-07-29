<?php

namespace DigitalElvis\NeuronAIStudio\Http\Middleware;

use Closure;
use DigitalElvis\NeuronAIStudio\Support\StudioLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStudioLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        StudioLocale::apply();

        return $next($request);
    }
}
