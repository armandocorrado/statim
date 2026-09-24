<?php

namespace App\Core\Patients\Http\Requests;

use App\Core\Patients\Enums\PatientSource;
use App\Core\Patients\Rules\ValidFiscalCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('patient'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fiscal_code' => $this->fiscal_code ? strtoupper($this->fiscal_code) : $this->fiscal_code,
            'address_province' => $this->address_province ? strtoupper($this->address_province) : $this->address_province,
            'residence_province' => $this->residence_province ? strtoupper($this->residence_province) : $this->residence_province,
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'fiscal_code' => ['nullable', 'string', 'size:16', new ValidFiscalCode],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_phone' => ['nullable', 'string', 'max:50'],
            'landline_phone' => ['nullable', 'string', 'max:50'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'address_city' => ['nullable', 'string', 'max:255'],
            'address_province' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'residence_street' => ['nullable', 'string', 'max:255'],
            'residence_postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'residence_city' => ['nullable', 'string', 'max:255'],
            'residence_province' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'vat_number' => ['nullable', 'regex:/^\d{11}$/'],
            'source' => ['nullable', new Enum(PatientSource::class)],
            'guardian_patient_id' => [
                'nullable', 'ulid',
                Rule::exists('patients', 'id'),
                Rule::notIn([$this->route('patient')->id]),
            ],
            'guardian_relationship' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }
}
