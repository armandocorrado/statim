<?php

namespace App\Core\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            'patient_id' => [
                'required', 'ulid',
                Rule::exists('patients', 'id'),
            ],
            'recipient_patient_id' => [
                'nullable', 'ulid',
                Rule::exists('patients', 'id'),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.vat_exemption_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
