<?php

namespace App\Http\Requests\Auth;

use App\Core\Tenancy\Models\TenantUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentifyLoginEmailRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
        ];
    }

    /**
     * Cerca l'email nel registro centrale e prepara la sessione per lo step
     * successivo: un solo studio -> direttamente alla password; piu' di uno
     * -> schermata di scelta. Nessuna email trovata: stesso identico errore
     * generico di una password sbagliata (mai "email non registrata") -
     * mitiga l'enumerazione lato messaggio, il rate limiting sotto la
     * mitiga lato tentativi ripetuti.
     */
    public function identify(): void
    {
        $this->ensureIsNotRateLimited();

        $memberships = TenantUser::query()
            ->where('email', $this->string('email')->toString())
            ->where('is_active', true)
            ->whereHas('tenant', fn ($query) => $query->where('is_active', true))
            ->get();

        if ($memberships->isEmpty()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $this->session()->put('login_pending_email', $this->string('email')->toString());

        if ($memberships->count() === 1) {
            $this->session()->put('login_pending_tenant_id', $memberships->first()->tenant_id);
            $this->session()->forget('login_pending_tenant_ids');
        } else {
            $this->session()->put('login_pending_tenant_ids', $memberships->pluck('tenant_id')->all());
            $this->session()->forget('login_pending_tenant_id');
        }
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
