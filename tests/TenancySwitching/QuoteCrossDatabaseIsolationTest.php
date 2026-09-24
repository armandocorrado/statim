<?php

use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\Quote;
use App\Core\Tenancy\Support\TenantConnectionResolver;

/**
 * Con database separati per studio, l'isolamento non è più garantito da
 * una Policy che confronta tenant_id — è la connessione stessa: un ID
 * creato nel DB dello studio B semplicemente non esiste nel DB dello
 * studio A, quindi la richiesta restituisce 404 (record non trovato),
 * non 403 (permesso negato). Stesso pattern di TenantIsolationTest.
 */
test('a quote cannot be viewed across tenants (separate physical databases)', function () {
    $studioB = provisionRealStudioWithRole('segreteria');
    $patientB = Patient::factory()->create();
    $quoteB = Quote::factory()->create(['patient_id' => $patientB->id]);
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('segreteria');

    $this->actingAs($studioA['user'])->get("/quotes/{$quoteB->id}")->assertNotFound();

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});

test('a billing document cannot be generated from another tenant quote (separate physical databases)', function () {
    $studioB = provisionRealStudioWithRole('segreteria');
    $patientB = Patient::factory()->create();
    $quoteB = Quote::factory()->accepted()->create(['patient_id' => $patientB->id]);
    app(TenantConnectionResolver::class)->release();

    $studioA = provisionRealStudioWithRole('segreteria');

    $this->actingAs($studioA['user'])->post("/quotes/{$quoteB->id}/generate-billing-document")->assertNotFound();

    app(TenantConnectionResolver::class)->release();
    unlink($studioA['path']);
    unlink($studioB['path']);
});
