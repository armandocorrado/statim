<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Patients\Models\Patient;
use App\Core\Quotes\Models\Quote;

test('an odontoiatra without the permission cannot edit quote prices', function () {
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($odontoiatra)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Otturazione', 'quantity' => 1, 'unit_price' => 90]],
    ])->assertForbidden();
});

test('admin can grant the price-edit permission to a specific odontoiatra, who can then edit prices', function () {
    $admin = userWithRole('admin');
    $odontoiatra = userWithRole('odontoiatra');
    $otherOdontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($odontoiatra)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Otturazione', 'quantity' => 1, 'unit_price' => 90]],
    ])->assertSessionHasNoErrors();

    expect($quote->lines()->count())->toBe(1);

    // Il permesso è per-utente, non per-ruolo: un altro odontoiatra dello
    // stesso tenant, non abilitato individualmente, resta bloccato.
    $this->actingAs($otherOdontoiatra)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Radiografia', 'quantity' => 1, 'unit_price' => 30]],
    ])->assertForbidden();
});

test('an odontoiatra enabled to edit prices still cannot issue, delete, or transition the quote', function () {
    $admin = userWithRole('admin');
    $odontoiatra = userWithRole('odontoiatra');
    $patient = Patient::factory()->create();
    $draftQuote = Quote::factory()->create(['patient_id' => $patient->id]);
    $issuedQuote = Quote::factory()->issued()->create(['patient_id' => $patient->id]);

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", ['enabled' => true]);

    $this->actingAs($odontoiatra)->patch("/quotes/{$draftQuote->id}/issue")->assertForbidden();
    $this->actingAs($odontoiatra)->delete("/quotes/{$draftQuote->id}")->assertForbidden();
    $this->actingAs($odontoiatra)->patch("/quotes/{$issuedQuote->id}/status", ['status' => 'accepted'])->assertForbidden();

    expect($draftQuote->fresh()->status->value)->toBe('draft');
});

test('igienista can also be individually enabled to edit prices', function () {
    $admin = userWithRole('admin');
    $igienista = userWithRole('igienista');
    $patient = Patient::factory()->create();
    $quote = Quote::factory()->create(['patient_id' => $patient->id]);

    $this->actingAs($admin)->patch("/users/{$igienista->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($igienista)->put("/quotes/{$quote->id}", [
        'lines' => [['description' => 'Igiene', 'quantity' => 1, 'unit_price' => 70]],
    ])->assertSessionHasNoErrors();
});

test('only admin can grant or revoke the price-edit permission', function () {
    $segreteria = userWithRole('segreteria');
    $odontoiatra = userWithRole('odontoiatra');

    $this->actingAs($segreteria)->patch("/users/{$odontoiatra->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertForbidden();

    $this->actingAs($odontoiatra)->patch("/users/{$odontoiatra->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertForbidden();
});

test('the permission cannot be granted to a non-dentist role', function () {
    $admin = userWithRole('admin');
    $segreteria = userWithRole('segreteria');
    $aso = userWithRole('aso');

    $this->actingAs($admin)->patch("/users/{$segreteria->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertInvalid(['enabled']);

    $this->actingAs($admin)->patch("/users/{$aso->id}/quote-price-permission", [
        'enabled' => true,
    ])->assertInvalid(['enabled']);
});

test('granting and revoking the price-edit permission is recorded in the audit log', function () {
    $admin = userWithRole('admin');
    $odontoiatra = userWithRole('odontoiatra');

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", ['enabled' => true]);

    $grantLog = AuditLog::where('action', 'quote_price_permission_granted')
        ->where('auditable_id', $odontoiatra->id)
        ->firstOrFail();
    expect($grantLog->new_values)->toBe(['treatment_plans.prices.edit' => true])
        ->and($grantLog->old_values)->toBe(['treatment_plans.prices.edit' => false])
        ->and($grantLog->user_id)->toBe($admin->id);

    $this->actingAs($admin)->patch("/users/{$odontoiatra->id}/quote-price-permission", ['enabled' => false]);

    $revokeLog = AuditLog::where('action', 'quote_price_permission_revoked')
        ->where('auditable_id', $odontoiatra->id)
        ->firstOrFail();
    expect($revokeLog->new_values)->toBe(['treatment_plans.prices.edit' => false])
        ->and($revokeLog->old_values)->toBe(['treatment_plans.prices.edit' => true]);
});
