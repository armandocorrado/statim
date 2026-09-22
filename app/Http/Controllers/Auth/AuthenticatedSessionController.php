<?php

namespace App\Http\Controllers\Auth;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\IdentifyLoginEmailRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SelectLoginStudioRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view — lo step mostrato dipende da cosa e' gia'
     * fissato in sessione dagli step precedenti (email->eventuale scelta
     * studio->password), non da un parametro dell'URL.
     */
    public function create(Request $request): Response
    {
        if ($request->boolean('restart')) {
            $this->forgetPendingLogin($request);
        }

        $pendingTenantId = $request->session()->get('login_pending_tenant_id');
        $pendingTenantIds = $request->session()->get('login_pending_tenant_ids');

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'step' => $pendingTenantId ? 'password' : ($pendingTenantIds ? 'choose-studio' : 'email'),
            'studio' => $pendingTenantId
                ? Tenant::query()->select(['id', 'name'])->find($pendingTenantId)
                : null,
            'studios' => $pendingTenantIds
                ? Tenant::query()->select(['id', 'name'])->whereIn('id', $pendingTenantIds)->get()
                : null,
        ]);
    }

    /**
     * Step 1: solo l'email. Decide se si passa dritti alla password (un
     * solo studio) o alla schermata di scelta (piu' di uno).
     */
    public function identify(IdentifyLoginEmailRequest $request): RedirectResponse
    {
        $request->identify();

        return redirect()->route('login');
    }

    /**
     * Step intermedio, solo quando l'email compare in piu' studi.
     */
    public function selectStudio(SelectLoginStudioRequest $request): RedirectResponse
    {
        $request->select();

        return redirect()->route('login');
    }

    /**
     * Step finale: la password, verificata sul DB dello studio gia' fissato
     * negli step precedenti.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        app(TenantConnectionResolver::class)->release();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function forgetPendingLogin(Request $request): void
    {
        $request->session()->forget(['login_pending_email', 'login_pending_tenant_id', 'login_pending_tenant_ids']);
    }
}
