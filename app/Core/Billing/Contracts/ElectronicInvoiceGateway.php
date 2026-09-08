<?php

namespace App\Core\Billing\Contracts;

use App\Core\Billing\Models\BillingDocument;

/**
 * Invio allo SdI (Sistema di Interscambio) — fattura elettronica.
 * Implementazione mock in questa fase, vedi CLAUDE.md per cosa serve
 * davvero (certificati, ambiente di test) prima di sostituirla con
 * un'implementazione reale.
 *
 * MAI chiamata per un documento il cui canale è SistemaTs — vedi
 * App\Core\Billing\Support\FiscalChannelResolver.
 */
interface ElectronicInvoiceGateway
{
    public function send(BillingDocument $document): FiscalSubmissionResult;
}
