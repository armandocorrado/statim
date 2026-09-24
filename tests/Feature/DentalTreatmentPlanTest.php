<?php

use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;

test('odontoiatra can create a treatment plan and view it', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

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
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManage', true)
            ->where('canManageHygieneOnly', true)
        );
});

test('segreteria can view a treatment plan but cannot manage its clinical content', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($segreteria)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canManage', false)
            ->where('canGenerateQuote', true)
        );

    $item = ServiceCatalogItem::factory()->create(['category' => 'general']);
    $this->actingAs($segreteria)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items", [
        'service_catalog_item_id' => $item->id, 'quantity' => 1, 'teeth' => ['16'],
    ])->assertForbidden();
});

test('aso cannot access a treatment plan at all', function () {
    $aso = userWithRole('aso');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($aso)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertForbidden();
    $this->actingAs($aso)->get("/patients/{$patient->id}/dental/treatment-plans")
        ->assertForbidden();
});

test('odontoiatra can add a general-category item with teeth to the plan', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $item = ServiceCatalogItem::factory()->create(['category' => 'general']);

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items", [
        'service_catalog_item_id' => $item->id, 'quantity' => 1, 'teeth' => ['16', '17'], 'notes' => 'Carie profonda',
    ]);

    $response->assertSessionHasNoErrors();
    $planItem = DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->firstOrFail();
    expect($planItem->service_catalog_item_id)->toBe($item->id)
        ->and($planItem->teeth()->pluck('tooth_number')->sort()->values()->all())->toBe(['16', '17']);
});

test('igienista can add a hygiene-category item but not a general-category one', function () {
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $hygieneItem = ServiceCatalogItem::factory()->hygiene()->create();
    $generalItem = ServiceCatalogItem::factory()->create(['category' => 'general']);

    $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items", [
        'service_catalog_item_id' => $hygieneItem->id, 'quantity' => 1,
    ])->assertSessionHasNoErrors();

    expect(DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->count())->toBe(1);

    $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items", [
        'service_catalog_item_id' => $generalItem->id, 'quantity' => 1,
    ])->assertForbidden();

    expect(DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->count())->toBe(1);
});

test('an invalid tooth number is rejected on a treatment plan item', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $item = ServiceCatalogItem::factory()->create();

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items", [
        'service_catalog_item_id' => $item->id, 'quantity' => 1, 'teeth' => ['99'],
    ])->assertInvalid(['teeth.0']);
});

test('editing one item does not touch the others', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $itemA = DentalTreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'quantity' => 1]);
    $itemB = DentalTreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'quantity' => 1]);
    $newCatalogItem = ServiceCatalogItem::factory()->create();

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items/{$itemA->id}", [
        'service_catalog_item_id' => $newCatalogItem->id, 'quantity' => 3, 'teeth' => ['26'],
    ])->assertSessionHasNoErrors();

    expect($itemA->fresh()->quantity)->toEqualWithDelta(3, 0.001)
        ->and($itemA->fresh()->service_catalog_item_id)->toBe($newCatalogItem->id)
        ->and($itemA->fresh()->teeth()->pluck('tooth_number')->all())->toBe(['26'])
        ->and((float) $itemB->fresh()->quantity)->toEqualWithDelta(1.0, 0.001)
        ->and(DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->count())->toBe(2);
});

test('deleting one item does not touch the others', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $itemA = DentalTreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);
    $itemB = DentalTreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);

    $this->actingAs($odontoiatra)->delete("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items/{$itemA->id}")
        ->assertSessionHasNoErrors();

    expect(DentalTreatmentPlanItem::where('treatment_plan_id', $plan->id)->pluck('id')->all())->toBe([$itemB->id]);
});

test('deleting an item also removes its linked teeth', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $item = DentalTreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);
    \App\Modules\Dental\Models\DentalTreatmentPlanItemTooth::factory()->create([
        'treatment_plan_item_id' => $item->id,
    ]);

    $this->actingAs($odontoiatra)->delete("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items/{$item->id}");

    expect(\Illuminate\Support\Facades\DB::table('dental_treatment_plan_item_teeth')->where('treatment_plan_item_id', $item->id)->count())->toBe(0);
});

test('igienista cannot delete a general-category item even though they can view the plan', function () {
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $generalItem = ServiceCatalogItem::factory()->create(['category' => 'general']);
    $item = DentalTreatmentPlanItem::factory()->create([
        'treatment_plan_id' => $plan->id, 'service_catalog_item_id' => $generalItem->id,
    ]);

    $this->actingAs($igienista)->delete("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items/{$item->id}")
        ->assertForbidden();

    expect(DentalTreatmentPlanItem::find($item->id))->not->toBeNull();
});

test('igienista can delete a hygiene-category item', function () {
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $hygieneItem = ServiceCatalogItem::factory()->hygiene()->create();
    $item = DentalTreatmentPlanItem::factory()->create([
        'treatment_plan_id' => $plan->id, 'service_catalog_item_id' => $hygieneItem->id,
    ]);

    $this->actingAs($igienista)->delete("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items/{$item->id}")
        ->assertSessionHasNoErrors();

    expect(DentalTreatmentPlanItem::find($item->id))->toBeNull();
});

test('segreteria cannot delete any treatment plan item', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $item = DentalTreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);

    $this->actingAs($segreteria)->delete("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/items/{$item->id}")
        ->assertForbidden();
});

test('an item cannot be edited or deleted from the wrong plan', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $planA = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $planB = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $itemOfPlanB = DentalTreatmentPlanItem::factory()->create(['treatment_plan_id' => $planB->id]);
    $catalogItem = ServiceCatalogItem::factory()->create();

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/treatment-plans/{$planA->id}/items/{$itemOfPlanB->id}", [
        'service_catalog_item_id' => $catalogItem->id, 'quantity' => 1,
    ])->assertStatus(404);

    $this->actingAs($odontoiatra)->delete("/patients/{$patient->id}/dental/treatment-plans/{$planA->id}/items/{$itemOfPlanB->id}")
        ->assertStatus(404);
});

test('a treatment plan cannot be accessed across patients', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $otherPatient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $otherPatient->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}")
        ->assertStatus(404);
});

test('plan notes are encrypted at rest', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/treatment-plans", [
        'notes' => 'Nota clinica riservata',
    ]);

    $plan = DentalTreatmentPlan::where('patient_id', $patient->id)->firstOrFail();
    $raw = \Illuminate\Support\Facades\DB::table('dental_treatment_plans')->where('id', $plan->id)->first();

    expect($raw->notes)->not->toBe('Nota clinica riservata')
        ->and($plan->notes)->toBe('Nota clinica riservata');
});
