<?php

use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;

test('odontoiatra can create a treatment plan and view it', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/treatment-plans", [
        'title' => 'Piano conservativo',
    ]);

    $response->assertSessionHasNoErrors();
    $plan = DentalTreatmentPlan::where('patient_id', $patient->id)->firstOrFail();

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dental/TreatmentPlans/Show')
            ->where('canManage', true)
            ->where('canManageHygieneOnly', false)
        );
});

test('igienista can view a treatment plan but with hygiene-only manage flag', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManage', true)
            ->where('canManageHygieneOnly', true)
        );
});

test('segreteria can view a treatment plan but cannot manage its clinical content', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($segreteria)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManage', false)
            ->where('canGenerateQuote', true)
        );

    $item = ServiceCatalogItem::factory()->create(['tenant_id' => $tenant->id, 'category' => 'general']);
    $this->actingAs($segreteria)->put("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}", [
        'items' => [
            ['service_catalog_item_id' => $item->id, 'quantity' => 1, 'teeth' => ['16']],
        ],
    ])->assertForbidden();
});

test('aso cannot access a treatment plan at all', function () {
    $tenant = Tenant::factory()->create();
    $aso = userForTenant($tenant, 'aso');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($aso)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertForbidden();
    $this->actingAs($aso)->get("/patients/{$patient->id}/dental/treatment-plans")
        ->assertForbidden();
});

test('odontoiatra can add a general-category item with teeth to the plan', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    $item = ServiceCatalogItem::factory()->create(['tenant_id' => $tenant->id, 'category' => 'general']);

    $response = $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}", [
        'items' => [
            ['service_catalog_item_id' => $item->id, 'quantity' => 1, 'teeth' => ['16', '17'], 'notes' => 'Carie profonda'],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $planItem = DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->firstOrFail();
    expect($planItem->service_catalog_item_id)->toBe($item->id)
        ->and($planItem->teeth()->pluck('tooth_number')->sort()->values()->all())->toBe(['16', '17']);
});

test('igienista can add a hygiene-category item but not a general-category one', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    $hygieneItem = ServiceCatalogItem::factory()->hygiene()->create(['tenant_id' => $tenant->id]);
    $generalItem = ServiceCatalogItem::factory()->create(['tenant_id' => $tenant->id, 'category' => 'general']);

    $this->actingAs($igienista)->put("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}", [
        'items' => [
            ['service_catalog_item_id' => $hygieneItem->id, 'quantity' => 1],
        ],
    ])->assertSessionHasNoErrors();

    expect(DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->count())->toBe(1);

    $this->actingAs($igienista)->put("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}", [
        'items' => [
            ['service_catalog_item_id' => $generalItem->id, 'quantity' => 1],
        ],
    ])->assertInvalid(['items.0.service_catalog_item_id']);
});

test('an invalid tooth number is rejected on a treatment plan item', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    $item = ServiceCatalogItem::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}", [
        'items' => [
            ['service_catalog_item_id' => $item->id, 'quantity' => 1, 'teeth' => ['99']],
        ],
    ])->assertInvalid(['items.0.teeth.0']);
});

test('updating a plan replaces all items in one go', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    DentalTreatmentPlanItem::factory()->create(['tenant_id' => $tenant->id, 'treatment_plan_id' => $plan->id]);

    $newItem = ServiceCatalogItem::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}", [
        'items' => [
            ['service_catalog_item_id' => $newItem->id, 'quantity' => 2],
        ],
    ])->assertSessionHasNoErrors();

    expect(DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->count())->toBe(1)
        ->and(DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->first()->service_catalog_item_id)->toBe($newItem->id);
});

test('a treatment plan cannot be accessed across patients', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $otherPatient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $plan = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $otherPatient->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertStatus(404);
});

test('a treatment plan cannot be accessed across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $odontoiatraA = userForTenant($tenantA, 'odontoiatra');
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);
    $planB = DentalTreatmentPlan::factory()->create(['tenant_id' => $tenantB->id, 'patient_id' => $patientB->id]);

    $this->actingAs($odontoiatraA)->get("/patients/{$patientB->id}/dental/treatment-plans/{$planB->id}")
        ->assertForbidden();
});

test('plan notes are encrypted at rest', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/treatment-plans", [
        'notes' => 'Nota clinica riservata',
    ]);

    $plan = DentalTreatmentPlan::where('patient_id', $patient->id)->firstOrFail();
    $raw = \Illuminate\Support\Facades\DB::table('dental_treatment_plans')->where('id', $plan->id)->first();

    expect($raw->notes)->not->toBe('Nota clinica riservata')
        ->and($plan->notes)->toBe('Nota clinica riservata');
});
