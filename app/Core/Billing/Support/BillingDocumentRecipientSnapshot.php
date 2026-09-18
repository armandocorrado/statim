<?php

namespace App\Core\Billing\Support;

use App\Core\Billing\Models\BillingDocument;
use App\Core\Patients\Models\Patient;

/**
 * Congela sul documento i dati fiscali del destinatario (paziente o
 * intestatario diverso) al momento della creazione — non un join live su
 * Patient, coerente col resto del modulo (vedi BillingDocument). Estratta
 * qui perché sia `BillingDocumentController` (creazione/modifica manuale)
 * sia la generazione da preventivo (QuoteController::generateBillingDocument())
 * devono applicare esattamente la stessa logica.
 */
class BillingDocumentRecipientSnapshot
{
    public static function apply(BillingDocument $document, string $patientId, ?string $recipientPatientId): void
    {
        $recipient = Patient::find($recipientPatientId ?? $patientId);

        if (! $recipient) {
            return;
        }

        $document->recipient_name = "{$recipient->first_name} {$recipient->last_name}";
        $document->recipient_fiscal_code = $recipient->fiscal_code;
        $document->recipient_vat_number = $recipient->vat_number;
        $document->recipient_address_street = $recipient->address_street;
        $document->recipient_address_postal_code = $recipient->address_postal_code;
        $document->recipient_address_city = $recipient->address_city;
        $document->recipient_address_province = $recipient->address_province;
    }
}
