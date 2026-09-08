<?php

namespace App\Core\Consents\Http\Requests;

use App\Core\Consents\Enums\ConsentCollectionMethod;
use App\Core\Consents\Enums\ConsentPurpose;
use App\Core\Consents\Enums\PolicyVersion;
use App\Core\Consents\Models\Consent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('record', [Consent::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        return [
            'purpose' => [
                'required', new Enum(ConsentPurpose::class),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $hasActiveConsent = Consent::query()
                        ->where('patient_id', $this->route('patient')->id)
                        ->where('purpose', $value)
                        ->whereNull('revoked_at')
                        ->exists();

                    if ($hasActiveConsent) {
                        $fail('Esiste già un consenso attivo per questa finalità: revocalo prima di registrarne uno nuovo.');
                    }
                },
            ],
            'collection_method' => ['required', new Enum(ConsentCollectionMethod::class)],
            'policy_version' => ['required', new Enum(PolicyVersion::class)],
            'granted_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
