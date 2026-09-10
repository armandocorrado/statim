<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Models\DentalDocument;

test('igienista sees only hygiene-section diary entries', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $hygieneEntry = DentalDiaryEntry::factory()->hygiene()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
    ]);
    DentalDiaryEntry::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
    ]); // general section

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental")
        ->assertInertia(fn ($page) => $page
            ->has('diaryEntries', 1)
            ->where('diaryEntries.0.id', $hygieneEntry->id)
        );
});

test('odontoiatra sees both general and hygiene diary entries', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    DentalDiaryEntry::factory()->hygiene()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    DentalDiaryEntry::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental")
        ->assertInertia(fn ($page) => $page->has('diaryEntries', 2));
});

test('igienista cannot add a general-section diary entry', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/diary", [
        'entry_date' => now()->toDateString(),
        'section' => 'general',
        'content' => 'Controllo occlusione',
    ]);

    $response->assertForbidden();
    expect(DentalDiaryEntry::where('patient_id', $patient->id)->count())->toBe(0);
});

test('igienista can add a hygiene-section diary entry', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/diary", [
        'entry_date' => now()->toDateString(),
        'section' => 'hygiene',
        'content' => 'Seduta di igiene orale',
    ]);

    $response->assertSessionHasNoErrors();
    $entry = DentalDiaryEntry::where('patient_id', $patient->id)->firstOrFail();
    expect($entry->section)->toBe(\App\Modules\Dental\Enums\DentalRecordSection::Hygiene)
        ->and($entry->operator_id)->toBe($igienista->id);
});

test('odontoiatra can add a diary entry in either section', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/diary", [
        'entry_date' => now()->toDateString(),
        'section' => 'hygiene',
        'content' => 'Nota su sezione igiene scritta dal dentista',
    ])->assertSessionHasNoErrors();

    expect(DentalDiaryEntry::where('patient_id', $patient->id)->count())->toBe(1);
});

test('a diary entry has no update or delete route', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $entry = DentalDiaryEntry::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/diary/{$entry->id}")
        ->assertStatus(404);
});

test('igienista sees only hygiene-section documents', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $hygieneDoc = DentalDocument::factory()->hygiene()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    DentalDocument::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental")
        ->assertInertia(fn ($page) => $page
            ->has('documents', 1)
            ->where('documents.0.id', $hygieneDoc->id)
        );
});

test('igienista cannot download a general-section document', function () {
    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $document = DentalDocument::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental/documents/{$document->id}/download")
        ->assertForbidden();
});
