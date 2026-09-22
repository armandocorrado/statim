<?php

namespace App\Http\Requests\Auth;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Support\TenantConnectionResolver;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ultimo step del login a due step: email e studio sono gia' fissati in
 * sessione dagli step precedenti (IdentifyLoginEmailRequest, ed
 * eventualmente SelectLoginStudioRequest) - qui arriva solo la password,
 * mai piu' l'email/tenant_id dal client.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $email = $this->session()->get('login_pending_email');
        $tenantId = $this->session()->get('login_pending_tenant_id');

        if (! $email || ! $tenantId) {
            throw ValidationException::withMessages([
                'password' => 'Sessione di login scaduta, ricomincia inserendo l\'email.',
            ]);
        }

        $this->ensureIsNotRateLimited($email);

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->is_active) {
            $this->forgetPendingLogin();

            throw ValidationException::withMessages([
                'password' => 'Sessione di login scaduta, ricomincia inserendo l\'email.',
            ]);
        }

        app(TenantConnectionResolver::class)->forTenant($tenant);

        if (! Auth::attempt(['email' => $email, 'password' => $this->string('password')->toString()], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($email));

            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey($email));

            throw ValidationException::withMessages([
                'password' => 'Questo account è stato disattivato.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($email));

        // Letto da RestoreTenantConnection a ogni richiesta successiva -
        // login_pending_* servivano solo al percorso email->studio->password,
        // da qui in poi lo studio autenticato e' questo.
        $this->session()->put('tenant_id', $tenant->id);
        $this->forgetPendingLogin();
    }

    private function forgetPendingLogin(): void
    {
        $this->session()->forget(['login_pending_email', 'login_pending_tenant_id', 'login_pending_tenant_ids']);
    }

    public function ensureIsNotRateLimited(string $email): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($email), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey($email));

        throw ValidationException::withMessages([
            'password' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(string $email): string
    {
        return Str::transliterate(Str::lower($email).'|'.$this->ip());
    }
}
