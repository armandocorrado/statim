<?php

namespace App\Core\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * "quotes.prices.edit" (App\Core\Quotes\Policies\QuotePolicy::PRICES_EDIT_PERMISSION)
 * è concedibile SOLO a odontoiatra/igienista — concederlo a segreteria
 * sarebbe ridondante con treatment_plans.administer che già hanno, ad
 * ASO romperebbe il vincolo "nessun accesso" già garantito altrove.
 */
class UpdateUserQuotePricePermissionRequest extends FormRequest
{
    private const ELIGIBLE_ROLES = ['odontoiatra', 'igienista'];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->route('user');

            if (! in_array($target->getRoleNames()->first(), self::ELIGIBLE_ROLES, true)) {
                $validator->errors()->add('enabled', 'Questa capacità è concedibile solo a odontoiatra o igienista.');
            }
        });
    }
}
