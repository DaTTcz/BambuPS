<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyToBearer
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-Api-Key');
        if ($apiKey && !$request->header('Authorization')) {
            $request->headers->set('Authorization', 'Bearer ' . $apiKey);
        }
        return $next($request);
    }
}
