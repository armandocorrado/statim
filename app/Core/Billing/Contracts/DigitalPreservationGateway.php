<?php

namespace App\Core\Billing\Contracts;

use App\Core\Billing\Models\BillingDocument;

/**
 * Conservazione a norma. Segnaposto: implementazione mock, nessuna logica
 * reale di archiviazione legale. Vedi CLAUDE.md per cosa servirà davvero
 * (un conservatore accreditato) prima di sostituirla.
 */
interface DigitalPreservationGateway
{
    public function preserve(BillingDocument $document): FiscalSubmissionResult;
}
