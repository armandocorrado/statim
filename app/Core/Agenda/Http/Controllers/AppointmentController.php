<?php

namespace App\Core\Agenda\Http\Controllers;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Http\Requests\StoreAppointmentRequest;
use App\Core\Agenda\Http\Requests\UpdateAppointmentRequest;
use App\Core\Agenda\Models\Appointment;
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
     */
    private function rejectIfOverlapping(array $data, string $tenantId, ?string $excluding = null): void
    {
        $overlaps = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->where('operator_id', $data['operator_id'])
            ->when($excluding, fn ($query) => $query->where('id', '!=', $excluding))
            ->whereNotIn('status', array_map(fn ($s) => $s->value, AppointmentStatus::excludedFromOverlapCheck()))
            ->where('start_at', '<', $data['end_at'])
            ->where('end_at', '>', $data['start_at'])
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'overlap' => "L'operatore ha già un appuntamento in questa fascia oraria.",
            ]);
        }
    }
}
