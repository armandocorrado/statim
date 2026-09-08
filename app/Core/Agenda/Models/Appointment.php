<?php

namespace App\Core\Agenda\Models;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Audit\Concerns\Auditable;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Punto d'accesso al paziente, non un semplice slot di calendario: collega
 * paziente + operatore + tempo, pensato per far convergere altri moduli
 * (piano di cura, contabilità, una futura Prestazione via appointment_id).
 * `patient_id` nullable = blocco/indisponibilità dell'operatore, non un
 * vero appuntamento clinico. Nessuna rotta di delete: annullare è un
 * cambio di stato (Cancelled), mai una cancellazione fisica.
 */
#[Fillable([
    'patient_id', 'operator_id', 'assistant_id', 'appointment_type_id',
    'start_at', 'end_at', 'status', 'notes',
])]
class Appointment extends Model
{
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function newFactory(): Factory
    {
        return AppointmentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'notes' => 'encrypted',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Chi assiste alla poltrona (in genere un ASO), se assegnato —
     * distinto dall'operatore che tratta il paziente.
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
