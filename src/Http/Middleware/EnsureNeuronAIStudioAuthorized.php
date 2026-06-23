<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNeuronAIStudioAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('neuronai-studio.gate', 'viewNeuronAIStudio');

        if (! app()->environment('local') && ! $request->user()) {
            abort(403, 'Unauthorized access to NeuronAI Studio.');
        }

        if (function_exists('gate') && ! gate()->allows($gate)) {
            abort(403, 'Unauthorized access to NeuronAI Studio.');
        }

        return $next($request);
    }
}
