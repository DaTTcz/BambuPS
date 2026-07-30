<?php

namespace App\Http\Middleware;

use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleName): Response
    {
        if (!Module::isEnabled($moduleName)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Modul není aktivován.',
                ], 403);
            }

            return redirect()->route('dashboard')->with('error', 'Tento modul není aktivován.');
        }

        return $next($request);
    }
}
