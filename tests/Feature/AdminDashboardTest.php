<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Models\AppointmentType;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\Quote;
use App\Core\Tenancy\Models\Tenant;

test('admin sees the direzionale dashboard with the summary row and the four charts', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');

    $type = AppointmentType::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Controllo']);

    // Today: 2 non-cancelled appointments (counted, one typed), 1
    // cancelled (excluded), 1 tomorrow (out of the 60-day-back window's
    // "today" bucket but still within range so also counted).
    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'appointment_type_id' => $type->id,
        'start_at' => now()->setTime(9, 0),
        'end_at' => now()->setTime(9, 30),
    ]);
    Appointment::factory()->create([
        'tenant_id' => $tenant->id,
        'start_at' => now()->setTime(11, 0),
        'end_at' => now()->setTime(11, 30),
    ]);
    Appointment::factory()->cancelled()->create([
        'tenant_id' => $tenant->id,
        'start_at' => now()->setTime(14, 0),
        'end_at' => now()->setTime(14, 30),
    ]);

    // Quotes: 1 draft (out of denominator), 1 issued-only (pending), 1 accepted.
    Quote::factory()->create(['tenant_id' => $tenant->id]);
    Quote::factory()->issued()->create(['tenant_id' => $tenant->id]);
    Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);

    // Billing: issued this month (counted), draft (not summed), issued last
    // month (out of the current-month bucket but still inside the 6-month window).
    BillingDocument::factory()->issued()->create([
        'tenant_id' => $tenant->id,
        'issued_at' => now(),
        'total_amount' => 250.00,
    ]);
    BillingDocument::factory()->create(['tenant_id' => $tenant->id]);
    BillingDocument::factory()->issued()->create([
        'tenant_id' => $tenant->id,
        'issued_at' => now()->subMonthNoOverflow(),
        'total_amount' => 999.00,
    ]);

    // Patients: 2 active, 1 inactive.
    Patient::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    Patient::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
    Patient::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

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
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');

    $response = $this->actingAs($odontoiatra)->get('/dashboard');

    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('the admin dashboard widgets are tenant-scoped', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = userForTenant($tenantA, 'admin');

    $typeB = AppointmentType::factory()->create(['tenant_id' => $tenantB->id]);

    Appointment::factory()->create([
        'tenant_id' => $tenantB->id,
        'appointment_type_id' => $typeB->id,
        'start_at' => now()->setTime(9, 0),
        'end_at' => now()->setTime(9, 30),
    ]);
    Quote::factory()->accepted()->create(['tenant_id' => $tenantB->id]);
    BillingDocument::factory()->issued()->create([
        'tenant_id' => $tenantB->id,
        'issued_at' => now(),
        'total_amount' => 500.00,
    ]);
    Patient::factory()->create(['tenant_id' => $tenantB->id, 'is_active' => true]);

    $response = $this->actingAs($adminA)->get('/dashboard');
    $today = now()->toDateString();

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard/Admin')
        ->where('summary.todayAppointments', 0)
        ->where('summary.quoteAcceptanceRate', null)
        ->where('summary.monthlyRevenue', 0)
        ->where('summary.activePatients', 0)
        ->where('quoteAcceptance.issued', 0)
        ->where('appointmentsByType', [])
        ->where('monthlyRevenue', fn ($series) => collect($series)->sum('total') == 0.0)
        ->where('appointmentsDaily', fn ($series) => collect($series)->firstWhere('date', $today)['count'] === 0)
    );
});
