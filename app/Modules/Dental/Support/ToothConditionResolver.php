<?php

namespace App\Modules\Dental\Support;

use App\Modules\Dental\Models\DentalToothCondition;
use Illuminate\Support\Collection;

/**
 * Deriva lo stato "corrente" di ogni dente di un paziente dallo storico
 * append-only: per ciascun tooth_number, il record più recente vince,
 * qualunque sia il suo condition_type (un solo simbolo per dente, non uno
 * stato per condition_type — coerente con un cartellino cartaceo
 * classico). Ordinato per recorded_date (la data clinica dell'evento,
 * eventualmente retrodatata in inserimento) e a parità di data per id:
 * gli ULID sono cronologicamente ordinabili, a differenza di created_at
 * che ha solo granularità al secondo — stesso accorgimento già adottato
 * per la numerazione dei documenti di fatturazione.
 */
class ToothConditionResolver
{
    /**
     * @return Collection<string, DentalToothCondition> keyed by tooth_number
     */
    public static function currentStates(string $patientId): Collection
    {
        return DentalToothCondition::query()
            ->where('patient_id', $patientId)
            ->orderByDesc('recorded_date')
            ->orderByDesc('id')
            ->get()
            ->unique('tooth_number')
            ->keyBy('tooth_number');
    }
}
