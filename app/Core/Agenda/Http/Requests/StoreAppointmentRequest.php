<?php

namespace App\Core\Agenda\Http\Requests;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Models\Appointment;
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
            ],
            'appointment_type_id' => [
                'nullable', 'ulid',
                Rule::exists('appointment_types', 'id')->where('tenant_id', $tenantId),
            ],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'overlap' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    $overlaps = Appointment::query()
                        ->where('operator_id', $this->input('operator_id'))
                        ->whereNotIn('status', array_map(fn ($s) => $s->value, AppointmentStatus::excludedFromOverlapCheck()))
                        ->where('start_at', '<', $this->input('end_at'))
                        ->where('end_at', '>', $this->input('start_at'))
                        ->exists();

                    if ($overlaps) {
                        $fail("L'operatore ha già un appuntamento in questa fascia oraria.");
                    }
                },
            ],
        ];
    }
}
