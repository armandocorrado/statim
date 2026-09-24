<?php

use App\Core\Patients\Models\Patient;
use App\Modules\Dental\Enums\DentalRecordSection;
use App\Modules\Dental\Models\DentalDiaryEntry;
use App\Modules\Dental\Models\DentalDocument;
use App\Modules\Dental\Models\DentalToothCondition;

test('odontoiatra can open the odontogram', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dental/Odontogram')
            ->where('canManage', true)
            ->has('permanentTeeth', 32)
            ->has('deciduousTeeth', 20)
        );
});

test('admin can open the odontogram', function () {
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();

    $this->actingAs($admin)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertOk();
});

test('igienista cannot open the odontogram', function () {
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $this->actingAs($igienista)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertForbidden();
});

test('segreteria and aso cannot open the odontogram', function () {
    $patient = Patient::factory()->create();

    foreach (['segreteria', 'aso'] as $role) {
        $user = userWithRole($role);

        $this->actingAs($user)->get("/patients/{$patient->id}/dental/odontogram")
            ->assertForbidden();
    }
});

test('opening the odontogram is recorded in the audit log', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram");

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'odontogram_viewed',
        'auditable_type' => Patient::class,
        'auditable_id' => $patient->id,
        'user_id' => $odontoiatra->id,
    ]);
});

test('odontoiatra can record a tooth condition', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
        'notes' => 'Carie occlusale',
    ]);

    $response->assertSessionHasNoErrors();
    $condition = DentalToothCondition::where('patient_id', $patient->id)->firstOrFail();
    expect($condition->tooth_number)->toBe('16')
        ->and($condition->condition_type)->toBe(\App\Modules\Dental\Enums\ToothCondition::Carious)
        ->and($condition->operator_id)->toBe($odontoiatra->id)
        ->and($condition->notes)->toBe('Carie occlusale');
});

test('an invalid tooth number is rejected', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '99',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
    ])->assertInvalid(['tooth_number']);
});

test('a deciduous tooth number is accepted', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '55',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(DentalToothCondition::where('patient_id', $patient->id)->where('tooth_number', '55')->exists())->toBeTrue();
});

test('igienista cannot record a tooth condition', function () {
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();

    $this->actingAs($igienista)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
    ])->assertForbidden();

    expect(DentalToothCondition::where('patient_id', $patient->id)->count())->toBe(0);
});

test('a tooth condition has no update or delete route', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $condition = DentalToothCondition::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->put("/patients/{$patient->id}/dental/odontogram/{$condition->id}")
        ->assertStatus(404);
    $this->actingAs($odontoiatra)->delete("/patients/{$patient->id}/dental/odontogram/{$condition->id}")
        ->assertStatus(404);
});

test('the current state of a tooth is the most recent record regardless of condition type', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    DentalToothCondition::factory()->create([
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'carious',
        'recorded_date' => now()->subDays(30)->toDateString(),
    ]);
    DentalToothCondition::factory()->create([
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
    ]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertInertia(fn ($page) => $page
            ->where('currentStates.26.condition_type', 'filled')
        );
});

test('full history of a tooth remains available even after a newer state is recorded', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    DentalToothCondition::factory()->create([
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'carious',
        'recorded_date' => now()->subDays(30)->toDateString(),
    ]);
    DentalToothCondition::factory()->create([
        'patient_id' => $patient->id,
        'tooth_number' => '26',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
    ]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertInertia(fn ($page) => $page->has('history', 2));
});

test('a tooth condition can be linked to an existing diary entry', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $diaryEntry = DentalDiaryEntry::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
        'diary_entry_id' => $diaryEntry->id,
    ])->assertSessionHasNoErrors();

    $condition = DentalToothCondition::where('patient_id', $patient->id)->firstOrFail();
    expect($condition->diary_entry_id)->toBe($diaryEntry->id);
});

test('a diary entry from another patient cannot be linked', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $otherPatient = Patient::factory()->create();
    $diaryEntry = DentalDiaryEntry::factory()->create(['patient_id' => $otherPatient->id]);

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'filled',
        'recorded_date' => now()->toDateString(),
        'diary_entry_id' => $diaryEntry->id,
    ])->assertInvalid(['diary_entry_id']);
});

test('notes on a tooth condition are encrypted at rest', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $this->actingAs($odontoiatra)->post("/patients/{$patient->id}/dental/odontogram", [
        'tooth_number' => '16',
        'condition_type' => 'carious',
        'recorded_date' => now()->toDateString(),
        'notes' => 'Nota clinica sensibile',
    ]);

    $condition = DentalToothCondition::where('patient_id', $patient->id)->firstOrFail();
    $raw = \Illuminate\Support\Facades\DB::table('dental_tooth_conditions')->where('id', $condition->id)->first();

    expect($raw->notes)->not->toBe('Nota clinica sensibile')
        ->and($condition->notes)->toBe('Nota clinica sensibile');
});

test('the tooth panel shows documents linked to that tooth', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    $document = DentalDocument::factory()->create(['patient_id' => $patient->id]);
    \App\Modules\Dental\Models\DentalDocumentTooth::factory()->create(['document_id' => $document->id, 'tooth_number' => '16']);

    $unrelatedDocument = DentalDocument::factory()->create(['patient_id' => $patient->id]);
    \App\Modules\Dental\Models\DentalDocumentTooth::factory()->create(['document_id' => $unrelatedDocument->id, 'tooth_number' => '27']);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertInertia(fn ($page) => $page
            ->has('documentsByTooth.16', 1)
            ->where('documentsByTooth.16.0.id', $document->id)
            ->where('documentsByTooth.27.0.id', $unrelatedDocument->id)
        );
});

test('a document with no tooth link does not appear in any tooth panel', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();

    DentalDocument::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertInertia(fn ($page) => $page->where('documentsByTooth', []));
});

test('documents linked to teeth of another patient are not leaked', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $otherPatient = Patient::factory()->create();

    $otherDocument = DentalDocument::factory()->create(['patient_id' => $otherPatient->id]);
    \App\Modules\Dental\Models\DentalDocumentTooth::factory()->create(['document_id' => $otherDocument->id, 'tooth_number' => '16']);

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/odontogram")
        ->assertInertia(fn ($page) => $page->where('documentsByTooth', []));
});

test('downloading a document reached through the tooth panel still enforces the existing document policy', function () {
    \Illuminate\Support\Facades\Storage::fake('local');

    $odontoiatra = userWithRole('odontoiatra');
    $segreteria = userWithRole('segreteria');
    $patient = Patient::factory()->create();

    $document = DentalDocument::factory()->create(['patient_id' => $patient->id]);
    \App\Modules\Dental\Models\DentalDocumentTooth::factory()->create(['document_id' => $document->id, 'tooth_number' => '16']);
    \Illuminate\Support\Facades\Storage::disk('local')->put($document->file_path, 'fake pdf content');

    $this->actingAs($segreteria)->get("/patients/{$patient->id}/dental/documents/{$document->id}/download")
        ->assertForbidden();

    $this->actingAs($odontoiatra)->get("/patients/{$patient->id}/dental/documents/{$document->id}/download")
        ->assertOk();
});
