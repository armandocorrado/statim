<?php

use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Models\Quote;
use App\Core\Quotes\Models\QuoteLine;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Modules\Dental\Models\DentalTreatmentPlan;
use App\Modules\Dental\Models\DentalTreatmentPlanItem;

test('segreteria can generate a quote from a treatment plan', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);
    $item = ServiceCatalogItem::factory()->create(['name' => 'Otturazione', 'base_price' => 90]);
    $planItem = DentalTreatmentPlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'service_catalog_item_id' => $item->id,
        'quantity' => 1,
    ]);
    \App\Modules\Dental\Models\DentalTreatmentPlanItemTooth::factory()->create([
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
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $plan = DentalTreatmentPlan::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/treatment-plans/{$plan->id}/generate-quote")
        ->assertForbidden();
});

test('aso cannot see quotes at all', function () {
    $aso = userWithRole('aso');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($aso)->get('/quotes')->assertForbidden();
    $this->actingAs($aso)->get("/quotes/{$quote->id}")->assertForbidden();
});

test('odontoiatra can view but not edit a quote', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->get("/quotes/{$quote->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    $this->actingAs($odontoiatra)->put("/quotes/{$quote->id}", ['lines' => []])
        ->assertForbidden();
});

test('segreteria can update draft quote lines and totals are computed on issue with discount and vat', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->create(['patient_id' => $patient->id]);

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
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'accepted'])
        ->assertForbidden();
});

test('an issued quote can be accepted, recording the response date', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->issued()->create(['patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'accepted'])
        ->assertSessionHasNoErrors();

    $quote->refresh();
    expect($quote->status)->toBe(QuoteStatus::Accepted)
        ->and($quote->responded_at)->not->toBeNull();
});

test('an accepted quote cannot jump directly to completed, must pass through in_progress', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->accepted()->create(['patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'completed'])
        ->assertInvalid(['status']);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'in_progress'])
        ->assertSessionHasNoErrors();
    expect($quote->fresh()->status)->toBe(QuoteStatus::InProgress);
});

test('a rejected quote is terminal, no further transitions allowed', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->rejected()->create(['patient_id' => $patient->id]);

    $this->actingAs($segreteria)->patch("/quotes/{$quote->id}/status", ['status' => 'in_progress'])
        ->assertInvalid(['status']);
});

test('acceptance rate counts accepted+in_progress+completed over every non-draft quote', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();

    Quote::factory()->create(['patient_id' => $patient->id]); // draft, excluded
    Quote::factory()->issued()->create(['patient_id' => $patient->id]); // issued, not yet responded
    Quote::factory()->accepted()->create(['patient_id' => $patient->id]);
    Quote::factory()->rejected()->create(['patient_id' => $patient->id]);

    $this->actingAs($segreteria)->get('/quotes')
        ->assertInertia(fn ($page) => $page
            ->where('acceptanceRate.issued', 3)
            ->where('acceptanceRate.accepted', 1)
            ->where('acceptanceRate.rate', round(1 / 3 * 100, 1))
        );
});

test('segreteria can generate a billing document from an accepted quote, discount frozen into the unit price', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create(['first_name' => 'Mario', 'last_name' => 'Rossi']);
    $quote = Quote::factory()->accepted()->create(['patient_id' => $patient->id]);
    QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'description' => 'Otturazione',
        'quantity' => 2,
        'unit_price' => 100,
        'discount_percent' => 10,
        'vat_rate' => null,
        'vat_exemption_reason' => 'art. 10 n. 18 DPR 633/72',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($segreteria)->post("/quotes/{$quote->id}/generate-billing-document");

    $response->assertSessionHasNoErrors();

    $document = BillingDocument::where('source_quote_id', $quote->id)->firstOrFail();
    expect($document->status->value)->toBe('draft')
        ->and($document->patient_id)->toBe($patient->id)
        ->and($document->recipient_name)->toBe('Mario Rossi')
        ->and($document->document_number)->toBeNull();

    // La fattura non ha un campo sconto separato: il 10% si "congela" nel
    // prezzo unitario (100 * 0.9 = 90), non compare come riga a parte.
    $line = $document->lines()->firstOrFail();
    expect((float) $line->unit_price)->toBe(90.0)
        ->and((float) $line->line_total)->toBe(180.0)
        ->and($line->description)->toBe('Otturazione')
        ->and($line->vat_exemption_reason)->toBe('art. 10 n. 18 DPR 633/72');
});

test('a quote can generate more than one billing document over time', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->completed()->create(['patient_id' => $patient->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id]);

    $this->actingAs($segreteria)->post("/quotes/{$quote->id}/generate-billing-document")->assertSessionHasNoErrors();
    $this->actingAs($segreteria)->post("/quotes/{$quote->id}/generate-billing-document")->assertSessionHasNoErrors();

    expect(BillingDocument::where('source_quote_id', $quote->id)->count())->toBe(2);
});

test('a billing document cannot be generated from a draft or rejected quote', function () {
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();
    $draft = Quote::factory()->create(['patient_id' => $patient->id]);
    $rejected = Quote::factory()->rejected()->create(['patient_id' => $patient->id]);

    $this->actingAs($segreteria)->post("/quotes/{$draft->id}/generate-billing-document")->assertForbidden();
    $this->actingAs($segreteria)->post("/quotes/{$rejected->id}/generate-billing-document")->assertForbidden();

    expect(BillingDocument::count())->toBe(0);
});

test('odontoiatra cannot generate a billing document even from an accepted quote', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->accepted()->create(['patient_id' => $patient->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id]);

    $this->actingAs($odontoiatra)->post("/quotes/{$quote->id}/generate-billing-document")->assertForbidden();
});

test('numbering continues seamlessly between manually created documents and ones generated from a quote', function () {
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();

    $this->actingAs($admin)->post('/billing', [
        'patient_id' => $patient->id,
        'lines' => [['description' => 'Visita', 'quantity' => 1, 'unit_price' => 80, 'vat_rate' => null]],
    ]);
    $manualDocument = BillingDocument::where('patient_id', $patient->id)->firstOrFail();
    $this->actingAs($admin)->patch("/billing/{$manualDocument->id}/issue");

    $quote = Quote::factory()->accepted()->create(['patient_id' => $patient->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $this->actingAs($admin)->post("/quotes/{$quote->id}/generate-billing-document");
    $generatedDocument = BillingDocument::where('source_quote_id', $quote->id)->firstOrFail();
    $this->actingAs($admin)->patch("/billing/{$generatedDocument->id}/issue");

    expect($manualDocument->fresh()->document_number)->toBe(1)
        ->and($generatedDocument->fresh()->document_number)->toBe(2);
});
