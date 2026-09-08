<?php

namespace App\Core\Agenda\Http\Controllers;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Http\Requests\StoreAppointmentRequest;
use App\Core\Agenda\Http\Requests\UpdateAppointmentRequest;
use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Support\AppointmentOverlapChecker;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $this->rejectIfOverlapping($data, $request->user()->tenant_id);

            $appointment = new Appointment($data);
            $appointment->status = AppointmentStatus::Scheduled;
            $appointment->created_by = $request->user()->id;
            $appointment->save();
        });

        return back()->with('success', 'Appuntamento creato.');
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $appointment, $request) {
            $this->rejectIfOverlapping($data, $request->user()->tenant_id, excluding: $appointment->id);

            $appointment->fill($data);
            $appointment->save();
        });

        return back()->with('success', 'Appuntamento aggiornato.');
    }

    /**
     * Ri-controlla la sovrapposizione dentro la transazione, con
     * lockForUpdate(), per chiudere la finestra di corsa critica tra due
     * richieste concorrenti che passerebbero entrambe la validazione della
     * FormRequest prima che l'altra scriva. Best-effort su MySQL/InnoDB
     * (gap lock su un range scan indicizzato), non una garanzia assoluta
     * come un vincolo nativo di range-exclusion — vedi CLAUDE.md.
     *
     * Controlla operatore e assistente separatamente ma con la stessa
     * verifica "occupato in qualunque ruolo" (vedi AppointmentOverlapChecker)
     * — non due corsie indipendenti, altrimenti una stessa persona
     * operatore in un appuntamento e assistente in un altro sovrapposto
     * passerebbe inosservata.
     */
    private function rejectIfOverlapping(array $data, string $tenantId, ?string $excluding = null): void
    {
        $operatorBusy = AppointmentOverlapChecker::personIsBusy(
            $tenantId, $data['operator_id'], $data['start_at'], $data['end_at'],
            excludingAppointmentId: $excluding, lock: true,
        );

        if ($operatorBusy) {
            throw ValidationException::withMessages([
                'overlap' => "L'operatore ha già un appuntamento in questa fascia oraria.",
            ]);
        }

        if (! empty($data['assistant_id'])) {
            $assistantBusy = AppointmentOverlapChecker::personIsBusy(
                $tenantId, $data['assistant_id'], $data['start_at'], $data['end_at'],
                excludingAppointmentId: $excluding, lock: true,
            );

            if ($assistantBusy) {
                throw ValidationException::withMessages([
                    'assistant_overlap' => "L'assistente ha già un appuntamento in questa fascia oraria.",
                ]);
            }
        }
    }
}
