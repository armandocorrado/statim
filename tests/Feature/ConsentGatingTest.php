<?php

use App\Core\Consents\Models\Consent;
use App\Core\Patients\Models\Patient;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSkipped;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\ConsentTestNotification;
use Tests\Fixtures\PlainTestNotification;

// Event::fake([...]) with a specific list fakes ONLY those event classes —
// NotificationSending (what EnforceConsentGate listens on) still dispatches
// for real and runs the real listener. Notification::fake() would have been
// wrong here: it replaces the whole channel manager and never dispatches
// NotificationSending at all, so the test would pass for the wrong reason
// (nothing sends because everything is faked, not because the gate blocked
// it). We only fake the two *outcome* events to assert on, downstream of
// the real gate check.
function fakeNotificationOutcomeEvents(): void
{
    Event::fake([NotificationSent::class, NotificationSkipped::class]);
}

test('a notification to a patient without a valid consent is blocked', function () {
    fakeNotificationOutcomeEvents();

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'paziente@example.test']);

    $patient->notify(new ConsentTestNotification);

    Event::assertDispatched(NotificationSkipped::class);
    Event::assertNotDispatched(NotificationSent::class);
});

test('a notification to a patient with an active matching consent is sent', function () {
    fakeNotificationOutcomeEvents();

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'paziente@example.test']);
    Consent::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'purpose' => 'marketing',
    ]);

    $patient->notify(new ConsentTestNotification);

    Event::assertDispatched(NotificationSent::class);
    Event::assertNotDispatched(NotificationSkipped::class);
});

test('a notification to a patient with a revoked consent is blocked', function () {
    fakeNotificationOutcomeEvents();

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'paziente@example.test']);
    Consent::factory()->revoked()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'purpose' => 'marketing',
    ]);

    $patient->notify(new ConsentTestNotification);

    Event::assertDispatched(NotificationSkipped::class);
    Event::assertNotDispatched(NotificationSent::class);
});

test('a notification to a patient with consent but no contact value for the channel is blocked', function () {
    fakeNotificationOutcomeEvents();

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => null]);
    Consent::factory()->create([
        'tenant_id' => $tenant->id,
        'patient_id' => $patient->id,
        'purpose' => 'marketing',
    ]);

    $patient->notify(new ConsentTestNotification);

    Event::assertDispatched(NotificationSkipped::class);
    Event::assertNotDispatched(NotificationSent::class);
});

test('a notification to a patient that does not declare a consent purpose is blocked by default', function () {
    fakeNotificationOutcomeEvents();

    $tenant = Tenant::factory()->create();
    $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'paziente@example.test']);

    $patient->notify(new PlainTestNotification);

    Event::assertDispatched(NotificationSkipped::class);
    Event::assertNotDispatched(NotificationSent::class);
});

test('a notification to a non-patient notifiable is unaffected by the consent gate', function () {
    fakeNotificationOutcomeEvents();

    $admin = userForTenant(Tenant::factory()->create(), 'admin');

    $admin->notify(new PlainTestNotification);

    Event::assertDispatched(NotificationSent::class);
    Event::assertNotDispatched(NotificationSkipped::class);
});
