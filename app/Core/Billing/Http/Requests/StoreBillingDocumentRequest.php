<?php

namespace App\Core\Billing\Http\Requests;

use App\Core\Billing\Models\BillingDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BillingDocument::class);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'patient_id' => [
                'required', 'ulid',
                Rule::exists('patients', 'id')->where('tenant_id', $tenantId),
            ],
            'recipient_patient_id' => [
                'nullable', 'ulid',
                Rule::exists('patients', 'id')->where('tenant_id', $tenantId),
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
