<?php

namespace App\Modules\Dental\Http\Requests;

use App\Modules\Dental\Enums\ToothCondition;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Models\DentalToothCondition;
use App\Modules\Dental\Support\FdiToothNumbers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreDentalToothConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createFor', [DentalToothCondition::class, $this->route('patient')]);
    }

    public function rules(): array
    {
        $patient = $this->route('patient');

        return [
            'tooth_number' => ['required', 'string', Rule::in(FdiToothNumbers::all())],
            'condition_type' => ['required', new Enum(ToothCondition::class)],
            'recorded_date' => ['required', 'date'],
            'diary_entry_id' => [
                'nullable',
                Rule::exists(DentalDiaryEntry::class, 'id')->where('patient_id', $patient->id),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
