<?php

use App\Core\Billing\Enums\BillingDocumentStatus;
use App\Core\Billing\Enums\FiscalChannel;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;

function billingLinePayload(array $overrides = []): array
{
    return array_merge([
        'description' => 'Visita di controllo',
        'quantity' => 1,
        'unit_price' => 80,
        'vat_rate' => null,
        'vat_exemption_reason' => 'Art. 10 n. 18 DPR 633/72 - prestazione sanitaria',
    ], $overrides);
}

test('segreteria can create a draft document', function () {
    $tenant = Tenant::factory()->create();
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($segreteria)->post('/billing', [
        'patient_id' => $patient->id,
        'lines' => [billingLinePayload()],
    ]);

    $response->assertSessionHasNoErrors();

    $document = BillingDocument::where('patient_id', $patient->id)->firstOrFail();
    expect($document->status)->toBe(BillingDocumentStatus::Draft)
        ->and($document->document_number)->toBeNull()
        ->and($document->lines)->toHaveCount(1)
        ->and($document->lines->first()->line_total)->toEqual('80.00');
});

test('odontoiatra cannot create a billing document', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post('/billing', [
        'patient_id' => $patient->id,
        'lines' => [billingLinePayload()],
    ]);

    $response->assertForbidden();
});

test('the recipient fiscal data is a frozen snapshot, not a live join', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'address_city' => 'Roma',
    ]);

    $this->actingAs($admin)->post('/billing', [
        'patient_id' => $patient->id,
        'lines' => [billingLinePayload()],
    ]);

    $document = BillingDocument::where('patient_id', $patient->id)->firstOrFail();
    expect($document->recipient_name)->toBe('Mario Rossi')
        ->and($document->recipient_address_city)->toBe('Roma');

    // Patient corrects their city afterwards — the already-created document
    // must not silently change.
    $patient->update(['address_city' => 'Milano']);

    expect($document->fresh()->recipient_address_city)->toBe('Roma');
});

test('a draft can be edited and deleted, an issued document cannot', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->post('/billing', [
        'patient_id' => $patient->id,
        'lines' => [billingLinePayload()],
    ]);
    $document = BillingDocument::where('patient_id', $patient->id)->firstOrFail();

    $this->actingAs($admin)->put("/billing/{$document->id}", [
        'patient_id' => $patient->id,
        'lines' => [billingLinePayload(['description' => 'Visita aggiornata'])],
    ])->assertSessionHasNoErrors();
    expect($document->lines()->first()->description)->toBe('Visita aggiornata');

    $this->actingAs($admin)->patch("/billing/{$document->id}/issue")
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)->put("/billing/{$document->id}", [
        'patient_id' => $patient->id,
        'lines' => [billingLinePayload()],
    ])->assertForbidden();

    $this->actingAs($admin)->delete("/billing/{$document->id}")
        ->assertForbidden();
});

test('issuing a document assigns a number, freezes totals and resolves the fiscal channel', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)->post('/billing', [
        'patient_id' => $patient->id,
        'lines' => [
            billingLinePayload(['unit_price' => 100, 'vat_rate' => null]),
            billingLinePayload(['description' => 'Prodotto', 'unit_price' => 50, 'vat_rate' => 22]),
        ],
    ]);
    $document = BillingDocument::where('patient_id', $patient->id)->firstOrFail();

    $this->actingAs($admin)->patch("/billing/{$document->id}/issue")
        ->assertSessionHasNoErrors();

    $document->refresh();
    expect($document->status)->toBe(BillingDocumentStatus::Issued)
        ->and($document->document_number)->toBe(1)
        ->and($document->document_year)->toBe((int) now()->format('Y'))
        ->and($document->total_taxable)->toEqual('150.00')
        ->and($document->total_vat)->toEqual('11.00')
        ->and($document->total_amount)->toEqual('161.00')
        ->and($document->fiscal_channel)->toBe(FiscalChannel::SistemaTs)
        ->and($document->external_reference)->not->toBeNull();
});

test('document numbers are sequential per tenant and year, without gaps', function () {
    $tenant = Tenant::factory()->create();
    $admin = userForTenant($tenant, 'admin');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $numbers = [];
    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($admin)->post('/billing', [
            'patient_id' => $patient->id,
            'lines' => [billingLinePayload()],
        ]);
        // created_at ha granularità al secondo: con più documenti creati
        // nello stesso secondo, latest('created_at') non li distingue in
        // modo affidabile. L'id ulid invece è ordinabile per creazione.
        $document = BillingDocument::where('patient_id', $patient->id)->orderByDesc('id')->first();
        $this->actingAs($admin)->patch("/billing/{$document->id}/issue");
        $numbers[] = $document->fresh()->document_number;
    }

    expect($numbers)->toBe([1, 2, 3]);
});

test('numbering is independent per tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = userForTenant($tenantA, 'admin');
    $adminB = userForTenant($tenantB, 'admin');
    $patientA = Patient::factory()->create(['tenant_id' => $tenantA->id]);
    $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($adminA)->post('/billing', ['patient_id' => $patientA->id, 'lines' => [billingLinePayload()]]);
    $documentA = BillingDocument::where('patient_id', $patientA->id)->firstOrFail();
    $this->actingAs($adminA)->patch("/billing/{$documentA->id}/issue");

    $this->actingAs($adminB)->post('/billing', ['patient_id' => $patientB->id, 'lines' => [billingLinePayload()]]);
    $documentB = BillingDocument::where('patient_id', $patientB->id)->firstOrFail();
    $this->actingAs($adminB)->patch("/billing/{$documentB->id}/issue");

    expect($documentA->fresh()->document_number)->toBe(1)
        ->and($documentB->fresh()->document_number)->toBe(1);
});

test('a document cannot be viewed across tenants', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminA = userForTenant($tenantA, 'admin');
    $documentB = BillingDocument::factory()->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($adminA)->get("/billing/{$documentB->id}")->assertForbidden();
});
