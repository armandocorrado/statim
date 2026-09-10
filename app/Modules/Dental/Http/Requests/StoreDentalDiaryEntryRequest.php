<?php

namespace App\Modules\Dental\Http\Requests;

use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalDiaryEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreDentalDiaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = DentalRecordSection::tryFrom((string) $this->input('section'));

        if (! $section) {
            return false;
        }

        return $this->user()->can('createFor', [DentalDiaryEntry::class, $this->route('patient'), $section]);
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'section' => ['required', new Enum(DentalRecordSection::class)],
            'content' => ['required', 'string', 'max:10000'],
        ];
    }
}
