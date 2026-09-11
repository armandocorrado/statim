<?php

use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Models\Quote;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;

test('segreteria can generate a quote from a treatment plan', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    $item = ServiceCatalogItem::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Otturazione', 'base_price' => 90]);
    $planItem = DentalTreatmentPlanItem::factory()->create([
        'tenant_id' => $tenant->id,
        'treatment_plan_id' => $plan->id,
        'service_catalog_item_id' => $item->id,
        'quantity' => 1,
    ]);
    \App\Modules\Dental\Models\DentalTreatmentPlanItemTooth::factory()->create([
        'tenant_id' => $tenant->id,
        'treatment_plan_item_id' => $planItem->id,
        'tooth_number' => '16',
    ]);

    $response = $this->actingAs($segreteria)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/generate-quote");

    $response->assertSessionHasNoErrors();
    $quote = Quote::where('source_treatment_plan_id', $plan->id)->firstOrFail();
    expect($quote->status)->toBe(QuoteStatus::Draft)
        ->and($quote->patient_id)->toBe($patient->id);

    $line = $quote->lines()->firstOrFail();
    expect($line->description)->toBe('Otturazione — dente 16')
        ->and((float) $line->unit_price)->toBe(90.0)
        ->and($line->source_treatment_plan_item_id)->toBe($planItem->id);
});

test('odontoiatra cannot generate a quote (administrative action)', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/generate-quote")
        ->assertForbidden();
});

test('aso cannot see quotes at all', function () {
    $tenant = Tenant::factory()->create();
    $aso = userForTenant($tenant, 'aso');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($aso)->get('/quotes')->assertForbidden();
    $this->actingAs($aso)->get("/quotes/{$quote->id}")->assertForbidden();
});

test('odontoiatra can view but not edit a quote', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->get("/quotes/{$quote->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    $this->actingAs($odontoiatra)->put("/quotes/{$quote->id}", ['lines' => []])
        ->assertForbidden();
});

test('segreteria can update draft quote lines and totals are computed on issue with discount and vat', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($segreteria)->put("/quotes/{$quote->id}", [
        'lines' => [
            ['description' => 'Otturazione', 'quantity' => 2, 'unit_price' => 100, 'discount_percent' => 10, 'vat_rate' => 22],
        ],
    ])->assertSessionHasNoErrors();

    $line = $quote->lines()->firstOrFail();
    // 2 * 100 * 0.9 = 180
    expect((float) $line->line_total)->toBe(180.0);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/issue")->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Issued)
        ->and((float) $quote->total_taxable)->toBe(180.0)
        ->and((float) $quote->total_vat)->toBe(round(180 * 0.22, 2))
        ->and($quote->issued_at)->not->toBeNull();
});

test('a draft quote cannot skip straight to accepted', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'accepted'])
        ->assertForbidden();
});

test('an issued quote can be accepted, recording the response date', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->issued()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'accepted'])
        ->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Accepted)
        ->and($quote->responded_at)->not->toBeNull();
});

test('an accepted quote cannot jump directly to completed, must pass through in_progress', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'completed'])
        ->assertInvalid(['status']);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'in_progress'])
        ->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe(QuoteStatus::InProgress);
});

test('a rejected quote is terminal, no further transitions allowed', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $quote = Quote::factory()->rejected()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'in_progress'])
        ->assertInvalid(['status']);
});

test('acceptance rate counts accepted+in_progress+completed over every non-draft quote', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    Quote::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]); // draft, excluded
    Quote::factory()->issued()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]); // issued, not yet responded
    Quote::factory()->accepted()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    Quote::factory()->rejected()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($segreteria)->get('/quotes')
        ->assertInertia(fn ($page) => $page
            ->where('acceptanceRate.issued', 3)
            ->where('acceptanceRate.accepted', 1)
            ->where('acceptanceRate.rate', round(1 / 3 * 100, 1))
        );
});

test('a quote cannot be viewed across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $segreteriaA = userForTenant($tenantA, 'segreteria');
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);
    $quoteB = Quote::factory()->create(['tenant_id' => $tenantB->id, 'patient_id' => $patientB->id]);

    $this->actingAs($segreteriaA)->get("/quotes/{$quoteB->id}")->assertForbidden();
});
