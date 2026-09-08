<?php

namespace App\Core\Billing\Contracts;

use App\Core\Billing\Models\BillingDocument;

/**
 * Invio al Sistema TS (Tessera Sanitaria) — spese sanitarie per il 730
 * precompilato. Implementazione mock in questa fase, vedi CLAUDE.md per
 * cosa serve davvero prima di sostituirla con un'implementazione reale.
 *
 * Un documento inviato qui non deve MAI passare anche da
 * ElectronicInvoiceGateway — vedi
 * App\Core\Billing\Support\FiscalChannelResolver.
 */
interface HealthExpenseReportingGateway
{
    public function report(BillingDocument $document): FiscalSubmissionResult;
}
