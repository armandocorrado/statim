<?php

namespace App\Core\Agenda\Http\Requests;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Support\AppointmentOverlapChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('appointment'));
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $appointment = $this->route('appointment');

        return [
            'patient_id' => [
                'nullable', 'ulid',
                Rule::exists('patients', 'id')->where('tenant_id', $tenantId),
            ],
            'operator_id' => [
                'required', 'ulid',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->user()->can('agenda.manage.all') && $value !== $this->user()->id) {
                        $fail('Non puoi assegnare questo appuntamento a un altro operatore.');
                    }
                },
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
            'status' => ['required', new Enum(AppointmentStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'overlap' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId, $appointment) {
                    $busy = AppointmentOverlapChecker::personIsBusy(
                        $tenantId, $this->input('operator_id'),
                        $this->input('start_at'), $this->input('end_at'),
                        excludingAppointmentId: $appointment->id,
                    );

                    if ($busy) {
                        $fail("L'operatore ha già un appuntamento in questa fascia oraria.");
                    }
                },
            ],
            'assistant_overlap' => [
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId, $appointment) {
                    if (! $this->input('assistant_id')) {
                        return;
                    }

                    $busy = AppointmentOverlapChecker::personIsBusy(
                        $tenantId, $this->input('assistant_id'),
                        $this->input('start_at'), $this->input('end_at'),
                        excludingAppointmentId: $appointment->id,
                    );

                    if ($busy) {
                        $fail("L'assistente ha già un appuntamento in questa fascia oraria.");
                    }
                },
            ],
        ];
    }
}
