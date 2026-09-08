<?php

namespace App\Core\Billing\Gateways;

use App\Core\Billing\Contracts\FiscalSubmissionResult;
use App\Core\Billing\Contracts\HealthExpenseReportingGateway;
use App\Core\Billing\Models\BillingDocument;
use Illuminate\Support\Str;

/**
 * Non chiama nulla di esterno: restituisce un riferimento finto, come se
 * il Sistema TS avesse accettato la comunicazione. Da sostituire con
 * un'implementazione reale nella fase dedicata — vedi CLAUDE.md.
 */
class MockHealthExpenseReportingGateway implements HealthExpenseReportingGateway
{
    public function report(BillingDocument $document): FiscalSubmissionResult
    {
        return new FiscalSubmissionResult(
            success: true,
            reference: 'MOCK-TS-'.Str::upper(Str::random(10)),
            message: 'Invio Sistema TS simulato (mock) — nessuna trasmissione reale.',
        );
    }
}
