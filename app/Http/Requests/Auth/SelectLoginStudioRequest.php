<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SelectLoginStudioRequest extends FormRequest
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
            'tenant_id' => ['required', 'string'],
        ];
    }

    /**
     * Fissa lo studio scelto solo se e' davvero tra quelli trovati per
     * quell'email nello step precedente (session, mai il valore del
     * client oltre questa verifica) - il client puo' scegliere solo tra le
     * opzioni che gli abbiamo mostrato noi, non inventarne altre.
     */
    public function select(): void
    {
        $pending = $this->session()->get('login_pending_tenant_ids', []);
        $chosen = $this->string('tenant_id')->toString();

        if (! in_array($chosen, $pending, true)) {
            throw ValidationException::withMessages([
                'tenant_id' => trans('auth.failed'),
            ]);
        }

        $this->session()->put('login_pending_tenant_id', $chosen);
        $this->session()->forget('login_pending_tenant_ids');
    }
}
