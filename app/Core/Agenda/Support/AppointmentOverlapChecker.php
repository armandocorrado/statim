<?php

namespace App\Core\Agenda\Support;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Models\Appointment;

/**
 * Un appuntamento coinvolge fino a due persone (operatore, assistente) e
 * nessuna delle due può essere impegnata altrove nello stesso momento — a
 * PRESCINDERE dal ruolo con cui è impegnata. Controllare "operatori
 * sovrapposti" e "assistenti sovrapposti" come due corsie indipendenti
 * lascerebbe passare il caso in cui la stessa persona è operatore in un
 * appuntamento e assistente in un altro, sovrapposti: per questo
 * `personIsBusy()` verifica sempre `operator_id OR assistant_id`,
 * qualunque sia il ruolo per cui la si sta assegnando ora.
 */
class AppointmentOverlapChecker
{
    public static function personIsBusy(
        string $personId,
        mixed $startAt,
        mixed $endAt,
        ?string $excludingAppointmentId = null,
        bool $lock = false,
    ): bool {
        $query = Appointment::query()
            ->where(fn ($q) => $q->where('operator_id', $personId)->orWhere('assistant_id', $personId))
            ->when($excludingAppointmentId, fn ($q) => $q->where('id', '!=', $excludingAppointmentId))
            ->whereNotIn('status', array_map(fn ($s) => $s->value, AppointmentStatus::excludedFromOverlapCheck()))
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }
}
