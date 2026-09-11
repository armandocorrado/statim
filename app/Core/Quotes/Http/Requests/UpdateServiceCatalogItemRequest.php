<?php

namespace App\Core\Quotes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('serviceCatalogItem'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'default_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_vat_exemption_reason' => ['nullable', 'string', 'max:255'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
