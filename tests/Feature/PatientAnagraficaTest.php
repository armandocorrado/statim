<?php

use App\Core\Patients\Models\Patient;
use Illuminate\Support\Facades\DB;

// Codice fiscale verificato: le somme intermedie di questo esempio sono
// pubblicate da fiscomania.com/carattere-controllo/ (dispari=46, pari=65,
// totale=111, 111 % 26 = 7 -> 'H'), non un fixture inventato.
const VALID_FISCAL_CODE = 'BBBTTT20H12X122H';

function baseFieldPatientPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
    ], $overrides);
}

test('a valid fiscal code passes validation', function () {
    $admin = userWithRole('admin');

    $response = $this->actingAs($admin)->post('/patients', baseFieldPatientPayload([
        'fiscal_code' => VALID_FISCAL_CODE,
    ]));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $patient = Patient::firstOrFail();
    expect($patient->fiscal_code)->toBe(VALID_FISCAL_CODE);
});

test('a fiscal code with a wrong check character is rejected', function () {
    $admin = userWithRole('admin');

    $badChecksum = substr(VALID_FISCAL_CODE, 0, 15).'A';

    $response = $this->actingAs($admin)->post('/patients', baseFieldPatientPayload([
        'fiscal_code' => $badChecksum,
    ]));

    $response->assertInvalid(['fiscal_code']);
});

test('a malformed fiscal code is rejected', function () {
    $admin = userWithRole('admin');

    $response = $this->actingAs($admin)->post('/patients', baseFieldPatientPayload([
        'fiscal_code' => 'NOTAFISCALCODE12',
    ]));

    $response->assertInvalid(['fiscal_code']);
});

test('the fiscal code is uppercased before validation', function () {
    $admin = userWithRole('admin');

    $response = $this->actingAs($admin)->post('/patients', baseFieldPatientPayload([
        'fiscal_code' => strtolower(VALID_FISCAL_CODE),
    ]));

    $response->assertSessionHasNoErrors();

    $patient = Patient::firstOrFail();
    expect($patient->fiscal_code)->toBe(VALID_FISCAL_CODE);
});

test('source must be one of the predefined values', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->post('/patients', baseFieldPatientPayload([
        'source' => 'not_a_real_source',
    ]))->assertInvalid(['source']);

    $this->actingAs($admin)->post('/patients', baseFieldPatientPayload([
        'source' => 'google',
    ]))->assertSessionHasNoErrors();
});

test('a patient cannot be their own guardian', function () {
    $admin = userWithRole('admin');
    $patient = Patient::factory()->create();

    $response = $this->actingAs($admin)->put("/patients/{$patient->id}", baseFieldPatientPayload([
        'guardian_patient_id' => $patient->id,
        'is_active' => true,
    ]));

    $response->assertInvalid(['guardian_patient_id']);
});

test('only the street is encrypted at rest, city/postal code/province stay queryable in plaintext', function () {
    $admin = userWithRole('admin');

    $this->actingAs($admin)->post('/patients', baseFieldPatientPayload([
        'mobile_phone' => '3331234567',
        'address_street' => 'Via Roma 1',
        'address_city' => 'Milano',
    ]))->assertSessionHasNoErrors();

    $raw = DB::table('patients')->firstOrFail();

    expect($raw->mobile_phone)->not->toBe('3331234567')
        ->and($raw->address_street)->not->toBe('Via Roma 1')
        ->and($raw->address_city)->toBe('Milano');
});
