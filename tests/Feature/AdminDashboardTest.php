<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Models\AppointmentType;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\Quote;

test('admin sees the direzionale dashboard with the summary row and the four charts', function () {
    $admin = userWithRole('admin');

    $type = AppointmentType::factory()->create(['name' => 'Controllo']);

    // Un unico paziente "di supporto" per appuntamenti/preventivi/fatture:
    // senza tenant_id a isolare i dati, le factory di Appointment/Quote/
    // BillingDocument creerebbero altrimenti un Patient nuovo ciascuna
    // (patient_id => Patient::factory() di default), inquinando il conteggio
    // di summary.activePatients più sotto. Inattivo apposta, per non
    // alterare l'aspettativa "2 attivi" già scritta per quel conteggio.
    $backingPatient = Patient::factory()->create(['is_active' => false]);

    // Today: 2 non-cancelled appointments (counted, one typed), 1
    // cancelled (excluded), 1 tomorrow (out of the 60-day-back window's
    // "today" bucket but still within range so also counted).
    Appointment::factory()->create([
        'patient_id' => $backingPatient->id,
        'appointment_type_id' => $type->id,
        'start_at' => now()->setTime(9, 0),
        'end_at' => now()->setTime(9, 30),
    ]);
    Appointment::factory()->create([
        'patient_id' => $backingPatient->id,
        'start_at' => now()->setTime(11, 0),
        'end_at' => now()->setTime(11, 30),
    ]);
    Appointment::factory()->cancelled()->create([
        'patient_id' => $backingPatient->id,
        'start_at' => now()->setTime(14, 0),
        'end_at' => now()->setTime(14, 30),
    ]);

    // Quotes: 1 draft (out of denominator), 1 issued-only (pending), 1 accepted.
    Quote::factory()->create(['patient_id' => $backingPatient->id]);
    Quote::factory()->issued()->create(['patient_id' => $backingPatient->id]);
    Quote::factory()->accepted()->create(['patient_id' => $backingPatient->id]);

    // Billing: issued this month (counted), draft (not summed), issued last
    // month (out of the current-month bucket but still inside the 6-month window).
    BillingDocument::factory()->issued()->create([
        'patient_id' => $backingPatient->id,
        'issued_at' => now(),
        'total_amount' => 250.00,
    ]);
    BillingDocument::factory()->create(['patient_id' => $backingPatient->id]);
    BillingDocument::factory()->issued()->create([
        'patient_id' => $backingPatient->id,
        'issued_at' => now()->subMonthNoOverflow(),
        'total_amount' => 999.00,
    ]);

    // Patients: 2 active, 1 inactive.
    Patient::factory()->create(['is_active' => true]);
    Patient::factory()->create(['is_active' => true]);
    Patient::factory()->create(['is_active' => false]);

    $response = $this->actingAs($admin)->get('/dashboard');
    $currentMonth = now()->format('Y-m');
    $today = now()->toDateString();

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard/Admin')
        ->where('summary.todayAppointments', 2)
        ->where('summary.quoteAcceptanceRate', 50)
        ->where('summary.monthlyRevenue', 250)
        ->where('summary.activePatients', 2)
        ->where('quoteAcceptance.issued', 2)
        ->where('quoteAcceptance.accepted', 1)
        ->where('quoteAcceptance.pending', 1)
        ->where('quoteAcceptance.rejected', 0)
        ->where('quoteAcceptance.rate', 50)
        ->where('appointmentsByType', [['name' => 'Controllo', 'total' => 1]])
        ->where('monthlyRevenue', fn ($series) => collect($series)->firstWhere('month', $currentMonth)['total'] == 250.0)
        ->where('monthlyRevenue', fn ($series) => collect($series)->firstWhere('month', now()->subMonthNoOverflow()->format('Y-m'))['total'] == 999.0)
        ->where('appointmentsDaily', fn ($series) => collect($series)->firstWhere('date', $today)['count'] === 2)
    );
});

test('a non-admin role keeps the generic dashboard, no direzionale widgets', function () {
    $odontoiatra = userWithRole('odontoiatra');

    $response = $this->actingAs($odontoiatra)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});
