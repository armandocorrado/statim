<?php

use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Models\DentalDocumentTooth;
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

test('a document can be linked to one or more teeth at upload time', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/documents", [
        'section' => 'general',
        'document_type' => 'radiografia',
        'description' => 'Endorale 16-17',
        'file' => UploadedFile::fake()->create('endorale.pdf', 50, 'application/pdf'),
        'teeth' => ['16', '17'],
    ]);

    $response->assertSessionHasNoErrors();
    $document = DentalDocument::where('patient_id', $patient->id)->firstOrFail();
    expect($document->teeth()->pluck('tooth_number')->sort()->values()->all())->toBe(['16', '17']);
});

test('a document without teeth (e.g. a whole-arch panoramic) links to none', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/documents", [
        'section' => 'general',
        'document_type' => 'panoramica',
        'file' => UploadedFile::fake()->create('opt.pdf', 50, 'application/pdf'),
    ]);

    $response->assertSessionHasNoErrors();
    $document = DentalDocument::where('patient_id', $patient->id)->firstOrFail();
    expect($document->teeth()->count())->toBe(0);
});

test('an invalid tooth number is rejected when linking a document', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/documents", [
        'section' => 'general',
        'document_type' => 'radiografia',
        'file' => UploadedFile::fake()->create('endorale.pdf', 50, 'application/pdf'),
        'teeth' => ['99'],
    ])->assertInvalid(['teeth.0']);
});

test('igienista can link teeth on a hygiene-section document they are allowed to upload', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $igienista = userForTenant($tenant, 'igienista');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/documents", [
        'section' => 'hygiene',
        'document_type' => 'foto',
        'file' => UploadedFile::fake()->create('foto.jpg', 50, 'image/jpeg'),
        'teeth' => ['26'],
    ]);

    $response->assertSessionHasNoErrors();
    $document = DentalDocument::where('patient_id', $patient->id)->firstOrFail();
    expect($document->teeth()->pluck('tooth_number')->all())->toBe(['26']);
});

test('a duplicate tooth number in the upload is only stored once', function () {
    Storage::fake('local');

    $tenant = Tenant::factory()->create();
    $odontoiatra = userForTenant($tenant, 'odontoiatra');
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/documents", [
        'section' => 'general',
        'document_type' => 'radiografia',
        'file' => UploadedFile::fake()->create('endorale.pdf', 50, 'application/pdf'),
        'teeth' => ['16', '16'],
    ])->assertSessionHasNoErrors();

    $document = DentalDocument::where('patient_id', $patient->id)->firstOrFail();
    expect($document->teeth()->count())->toBe(1);
});

test('document-tooth links are truly append-only, no updated_at column', function () {
    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
    $document = DentalDocument::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);
    DentalDocumentTooth::factory()->create(['tenant_id' => $tenant->id, 'document_id' => $document->id]);

    expect(\Illuminate\Support\Facades\Schema::hasColumn('dental_document_teeth', 'updated_at'))->toBeFalse();
});
