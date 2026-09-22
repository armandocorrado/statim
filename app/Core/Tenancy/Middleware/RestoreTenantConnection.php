<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ristabilisce, a ogni richiesta, la connessione dello studio scelto al
 * login (session('tenant_id')) — deve girare PRIMA di SubstituteBindings,
 * altrimenti un model binding implicito su una rotta autenticata
 * risolverebbe ancora sulla connessione sbagliata (stesso punto critico gia'
 * documentato per il vecchio meccanismo IdentifyTenant/tenant_id). Per
 * questo e' nel gruppo globale 'web' in bootstrap/app.php, non un alias di
 * rotta come 'tenant' (IdentifyTenant, invariato: continua a occuparsi
 * dello scoping tenant_id DENTRO la connessione qui gia' stabilita).
 *
 * Nessuna sessione con tenant_id (utente non loggato, o sessione precedente
 * a questa tappa) -> no-op: l'app resta sulla connessione di default
 * normale, comportamento identico a prima di questa tappa.
 */
class RestoreTenantConnection
{
    public function __construct(private readonly TenantConnectionResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('tenant_id');

        if (! $tenantId) {
            return $next($request);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->is_active) {
            $request->session()->forget('tenant_id');

            return $next($request);
        }

        $this->resolver->forTenant($tenant);

        return $next($request);
    }
}
