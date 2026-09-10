<?php

namespace App\Modules\Dental\Http\Requests;

use App\Modules\Dental\Models\DentalAnamnesis;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDentalAnamnesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createFor', [DentalAnamnesis::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        return [
            'pathologies' => ['nullable', 'string', 'max:5000'],
            'medications' => ['nullable', 'string', 'max:5000'],
            'risk_factors' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
