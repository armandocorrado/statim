<?php

namespace App\Modules\Dental\Http\Requests;

use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Support\FdiToothNumbers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreDentalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = DentalRecordSection::tryFrom((string) $this->input('section'));

        if (! $section) {
            return false;
        }

        return $this->user()->can('createFor', [DentalDocument::class, $this->route('patient'), $section]);
    }

    public function rules(): array
    {
        return [
            'section' => ['required', new Enum(DentalRecordSection::class)],
            'document_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            // Facoltativo e multiplo: un'endorale può riguardare 1-2 denti,
            // un OPT d'insieme nessuno in particolare.
            'teeth' => ['nullable', 'array'],
            'teeth.*' => ['string', Rule::in(FdiToothNumbers::all())],
        ];
    }
}
