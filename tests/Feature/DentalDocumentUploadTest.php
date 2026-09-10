<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('odontoiatra can upload a clinical document to private storage', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $file = UploadedFile::fake()->create('referto.pdf', 100, 'application/pdf');

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/documents", [
        'section' => 'general',
        'document_type' => 'referto',
        'description' => 'Referto radiografico',
        'file' => $file,
    ]);

    $response->assertSessionHasNoErrors();

    $document = DentalDocument::where('patient_id', $patient->id)->firstOrFail();
    expect($document->original_filename)->toBe('referto.pdf')
        ->and($document->mime_type)->toBe('application/pdf');

    Storage::disk('local')->assertExists($document->file_path);
});

test('an unsupported file type is rejected', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $file = UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload');

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/documents", [
        'section' => 'general',
        'document_type' => 'altro',
        'file' => $file,
    ])->assertInvalid(['file']);
});

test('a document download requires clinical access and section match', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $segreteria = userForTenant($tenant, 'segreteria');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $document = DentalDocument::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    Storage::disk('local')->put($document->file_path, 'fake pdf content');

    $this->actingAs($segreteria)->get("/patients/{$patient->id}/dental/documents/{$document->id}/download")
        ->assertForbidden();

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/documents/{$document->id}/download")
        ->assertOk();
});

test('a dental document has no update or delete route', function () {
    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $document = DentalDocument::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->delete("/patients/{$patient->id}/dental/documents/{$document->id}")
        ->assertStatus(404);
});
