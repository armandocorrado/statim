<?php

use App\Core\Agenda\Models\Appointment;
use App\Core\Agenda\Models\AppointmentType;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\Quote;
use App\Core\Tenancy\Support\TenantConnectionResolver;

test('the admin dashboard widgets are scoped to the studio\'s own physical database', function () {
    $studioB = provisionRealStudioWithRole('admin');

    $typeB = AppointmentType::factory()->create();
    Appointment::factory()->create([
        'appointment_type_id' => $typeB->id,
        'start_at' => now()->setTime(9, 0),
        'end_at' => now()->setTime(9, 30),
    ]);
    Quote::factory()->accepted()->create();
    BillingDocument::factory()->issued()->create([
        'issued_at' => now(),
        'total_amount' => 500.00,
    ]);
    Patient::factory()->create(['is_active' => true]);
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('admin');

    $response = $this->actingAs($studioA['user'])->get('/dashboard');
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

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});
