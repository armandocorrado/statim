<?php

namespace App\Core\Quotes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('quote'));
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.service_catalog_item_id' => [
                'nullable', 'ulid',
                Rule::exists('service_catalog_items', 'id')->where('tenant_id', $tenantId),
            ],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.vat_exemption_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
