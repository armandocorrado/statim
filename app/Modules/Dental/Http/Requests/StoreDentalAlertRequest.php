<?php

namespace App\Modules\Dental\Http\Requests;

use App\Modules\Dental\Enums\DentalAlertCategory;
use App\Modules\Dental\Models\DentalAlert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDentalAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createFor', [DentalAlert::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', new Enum(DentalAlertCategory::class)],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
