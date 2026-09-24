<?php

namespace App\Core\Billing\Support;

use App\Core\Billing\Models\BillingDocumentCounter;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Assegna numeri progressivi, senza buchi, per anno (si azzera il 1°
 * gennaio) — un database per studio, non serve più scopare per tenant.
 * DEVE essere chiamato dentro una DB::transaction() già aperta dal
 * chiamante — qui dentro si limita a lockare e incrementare la riga
 * contatore, non apre una propria transazione.
 *
 * Perché una tabella contatore dedicata e non un semplice
 * MAX(document_number)+1: quest'ultimo, anche con lockForUpdate(), non
 * protegge dal primo documento dell'anno — un SELECT che blocca un set di
 * righe vuoto (nessun documento ancora emesso quell'anno) non impedisce a
 * due richieste concorrenti di inserire entrambe il numero 1. La riga
 * contatore esiste invece SEMPRE una volta inizializzata, quindi c'è
 * sempre qualcosa da lockare.
 */
class BillingDocumentNumberer
{
    public static function next(int $year): int
    {
        $counter = self::lockCounterRow($year);

        if (! $counter) {
            try {
                $counter = BillingDocumentCounter::create(['year' => $year, 'next_number' => 1]);
            } catch (UniqueConstraintViolationException) {
                // Un'altra transazione concorrente l'ha creata per prima
                // (stessa finestra di corsa critica del primo documento
                // dell'anno) — lockiamo la sua riga e procediamo da lì.
                $counter = self::lockCounterRow($year);
            }
        }

        $number = $counter->next_number;
        $counter->next_number = $number + 1;
        $counter->save();

        return $number;
    }

    private static function lockCounterRow(int $year): ?BillingDocumentCounter
    {
        return BillingDocumentCounter::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->first();
    }
}
