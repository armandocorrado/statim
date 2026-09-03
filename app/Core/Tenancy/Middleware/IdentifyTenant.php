<?php

namespace App\Core\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $tenant = $user->tenant;

        abort_if(! $tenant || ! $tenant->is_active, 403, 'Studio non attivo.');

        app()->instance('currentTenant', $tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        return $next($request);
    }
}
