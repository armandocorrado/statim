<?php

namespace Database\Seeders;

use App\Core\Agenda\Enums\AppointmentStatus;
use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Models\AppointmentType;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popola l'agenda degli odontoiatri demo con qualche appuntamento — senza
 * dati, le viste multi-operatore dell'Agenda sono vuote e non dicono
 * niente in demo. Solo odontoiatri ("i dottori"): igienisti/aso restano
 * senza appuntamenti propri in questa passata.
 *
 * Idempotente: se un operatore ha già almeno un appuntamento, lo salta —
 * rilanciare il seeder non raddoppia gli appuntamenti. Rispetta comunque
 * il vincolo anti-sovrapposizione già esistente (orari distinti per
 * operatore), quindi non serve un controllo separato qui. Opera solo sui
 * tenant demo noti per slug, come DemoTeamSeeder.
 */
class DemoAppointmentSeeder extends Seeder
{
    /**
     * Offset in giorni da oggi e orario di inizio per ciascun appuntamento
     * demo di un operatore — stesso schema per tutti gli operatori, gli
     * orari non collidono mai tra loro perché ogni operatore ha la propria
     * agenda indipendente (l'anti-sovrapposizione è per operatore).
     */
    private const SLOTS = [
        ['day_offset' => 0, 'hour' => 9, 'minute' => 0],
        ['day_offset' => 0, 'hour' => 11, 'minute' => 0],
        ['day_offset' => 0, 'hour' => 15, 'minute' => 30],
        ['day_offset' => 1, 'hour' => 10, 'minute' => 0],
        ['day_offset' => 2, 'hour' => 9, 'minute' => 30],
    ];

    public function run(): void
    {
        $this->seedForTenant('studio-rossi');
        $this->seedForTenant('studio-bianchi');
    }

    private function seedForTenant(string $slug): void
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $admin = User::where('tenant_id', $tenant->id)->role('admin')->first();
        $odontoiatri = User::where('tenant_id', $tenant->id)->role('odontoiatra')->orderBy('name')->get();
        $patients = Patient::where('tenant_id', $tenant->id)->get();
        $types = AppointmentType::where('tenant_id', $tenant->id)->get();

        if (! $admin || $odontoiatri->isEmpty() || $patients->isEmpty()) {
            return;
        }

        foreach ($odontoiatri as $operator) {
            if (Appointment::where('operator_id', $operator->id)->exists()) {
                continue;
            }

            foreach (self::SLOTS as $index => $slot) {
                $start = now()->startOfDay()->addDays($slot['day_offset'])
                    ->setTime($slot['hour'], $slot['minute']);

                // tenant_id/created_by/status non sono mass-assignable
                // (Appointment::$fillable li esclude apposta — un form web
                // non deve poterli impostare): stesso pattern di
                // AppointmentController::store(), non Appointment::create().
                $appointment = new Appointment([
                    'patient_id' => $patients[$index % $patients->count()]->id,
                    'operator_id' => $operator->id,
                    'appointment_type_id' => $types->isNotEmpty() ? $types[$index % $types->count()]->id : null,
                    'start_at' => $start,
                    'end_at' => (clone $start)->addMinutes(30),
                ]);
                $appointment->tenant_id = $tenant->id;
                $appointment->status = $index === 0 ? AppointmentStatus::Confirmed : AppointmentStatus::Scheduled;
                $appointment->created_by = $admin->id;
                $appointment->save();
            }
        }
    }
}
