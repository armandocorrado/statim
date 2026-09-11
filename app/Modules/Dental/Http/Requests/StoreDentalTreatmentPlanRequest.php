<?php

namespace App\Modules\Dental\Http\Requests;

use App\Modules\Dental\Models\DentalTreatmentPlan;
use Illuminate\Foundation\Http\FormRequest;

class StoreDentalTreatmentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createFor', [DentalTreatmentPlan::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
