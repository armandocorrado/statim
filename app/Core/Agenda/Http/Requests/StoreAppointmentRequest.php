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
        $tenantId = $this->user()->tenant_id;

        return [
            'patient_id' => [
                'nullable', 'ulid',
                Rule::exists('patients', 'id')->where('tenant_id', $tenantId),
            ],
            'operator_id' => [
                'required', 'ulid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'assistant_id' => [
                'nullable', 'ulid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value === $this->input('operator_id')) {
                        $fail("L'assistente non può coincidere con l'operatore.");
                    }
                },
            ],
            'appointment_type_id' => [
                'nullable', 'ulid',
                Rule::exists('appointment_types', 'id')->where('tenant_id', $tenantId),
            ],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'overlap' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId) {
                    $busy = AppointmentOverlapChecker::personIsBusy(
                        $tenantId, $this->input('operator_id'),
                        $this->input('start_at'), $this->input('end_at'),
                    );

                    if ($busy) {
                        $fail("L'operatore ha già un appuntamento in questa fascia oraria.");
                    }
                },
            ],
            'assistant_overlap' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId) {
                    if (! $this->input('assistant_id')) {
                        return;
                    }

                    $busy = AppointmentOverlapChecker::personIsBusy(
                        $tenantId, $this->input('assistant_id'),
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
