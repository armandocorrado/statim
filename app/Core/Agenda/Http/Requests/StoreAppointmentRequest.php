<?php

namespace App\Core\Agenda\Http\Requests;

use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Support\AppointmentOverlapChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Appointment::class, $this->input('operator_id')]);
    }

    public function rules(): array
    {
        return [
            'patient_id' => [
                'nullable', 'ulid',
                Rule::exists('patients', 'id'),
            ],
            'operator_id' => [
                'required', 'ulid',
                Rule::exists('users', 'id'),
            ],
            'assistant_id' => [
                'nullable', 'ulid',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value === $this->input('operator_id')) {
                        $fail("L'assistente non può coincidere con l'operatore.");
                    }
                },
            ],
            'appointment_type_id' => [
                'nullable', 'ulid',
                Rule::exists('appointment_types', 'id'),
            ],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'overlap' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    $busy = AppointmentOverlapChecker::personIsBusy(
                        $this->input('operator_id'),
                        $this->input('start_at'), $this->input('end_at'),
                    );

                    if ($busy) {
                        $fail("L'operatore ha già un appuntamento in questa fascia oraria.");
                    }
                },
            ],
            'assistant_overlap' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->input('assistant_id')) {
                        return;
                    }

                    $busy = AppointmentOverlapChecker::personIsBusy(
                        $this->input('assistant_id'),
                        $this->input('start_at'), $this->input('end_at'),
                    );

                    if ($busy) {
                        $fail("L'assistente ha già un appuntamento in questa fascia oraria.");
                    }
                },
            ],
        ];
    }
}
