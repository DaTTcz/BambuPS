<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Appka je dostupná přes víc cest současně (vnitřní IP i doména za
 * Nginx Proxy Managerem). Pevné APP_URL v .env stačí jen na jednu z nich -
 * pro tu druhou by pak nesedělo generování podepsaných URL (např. Livewire
 * upload), protože podpis počítaný podle APP_URL neodpovídá tomu, na
 * kterou adresu se prohlížeč skutečně ptá (chyba 422).
 *
 * Tenhle middleware při KAŽDÉM požadavku přenastaví "kořenovou" URL na tu,
 * kterou prohlížeč skutečně použil - funguje to díky trustProxies() v
 * bootstrap/app.php, který appce dovolí věřit X-Forwarded-* hlavičkám od
 * reverse proxy (jinak by appka i za proxy pořád viděla jen interní
 * IP/port, ne skutečnou doménu, kterou vidí uživatel).
 */
class DynamicAppUrl
{
    public function handle(Request $request, Closure $next)
    {
        URL::forceRootUrl($request->getSchemeAndHttpHost());

        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
