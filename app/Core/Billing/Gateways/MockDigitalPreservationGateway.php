<?php

namespace App\Core\Billing\Gateways;

use App\Core\Billing\Contracts\DigitalPreservationGateway;
use App\Core\Billing\Contracts\FiscalSubmissionResult;
use App\Core\Billing\Models\BillingDocument;
use Illuminate\Support\Str;

/**
 * Segnaposto: nessuna conservazione a norma reale avviene qui. Da
 * sostituire con un'implementazione reale (conservatore accreditato)
 * nella fase dedicata — vedi CLAUDE.md.
 */
class MockDigitalPreservationGateway implements DigitalPreservationGateway
{
    public function preserve(BillingDocument $document): FiscalSubmissionResult
    {
        return new FiscalSubmissionResult(
            success: true,
            reference: 'MOCK-CONS-'.Str::upper(Str::random(10)),
            message: 'Conservazione simulata (mock) — nessuna archiviazione legale reale.',
        );
    }
}
