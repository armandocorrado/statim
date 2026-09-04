<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\PermissionRegistrar;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // Lazily evaluated: this middleware runs in the global `web`
                // group, before the `tenant` route middleware sets spatie's
                // team id — so the team id is set explicitly here from the
                // user's own tenant_id rather than relying on that order.
                'permissions' => function () use ($request) {
                    $user = $request->user();

                    if (! $user) {
                        return [];
                    }

                    app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

                    return $user->getAllPermissions()->pluck('name');
                },
            ],
        ];
    }
}
