<?php

namespace App\Core\Billing\Gateways;

use App\Core\Billing\Contracts\ElectronicInvoiceGateway;
use App\Core\Billing\Contracts\FiscalSubmissionResult;
use App\Core\Billing\Models\BillingDocument;
use Illuminate\Support\Str;

/**
 * Non chiama nulla di esterno: restituisce un riferimento finto, come se
 * lo SdI avesse accettato l'invio. Da sostituire con un'implementazione
 * reale (certificati, credenziali, ambiente di test) nella fase dedicata
 * — vedi CLAUDE.md.
 */
class MockElectronicInvoiceGateway implements ElectronicInvoiceGateway
{
    public function send(BillingDocument $document): FiscalSubmissionResult
    {
        return new FiscalSubmissionResult(
            success: true,
            reference: 'MOCK-SDI-'.Str::upper(Str::random(10)),
            message: 'Invio SdI simulato (mock) — nessuna trasmissione reale.',
        );
    }
}
