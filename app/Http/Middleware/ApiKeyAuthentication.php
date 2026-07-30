<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Pokud přijde X-Api-Key, převedeme ho na Authorization Bearer
        $apiKey = $request->header('X-Api-Key');

        if ($apiKey && !$request->header('Authorization')) {
            $request->headers->set('Authorization', 'Bearer ' . $apiKey);
        }

        return $next($request);
    }
}
