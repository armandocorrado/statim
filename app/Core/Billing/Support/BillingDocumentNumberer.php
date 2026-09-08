<?php

namespace App\Core\Billing\Support;

use App\Core\Billing\Models\BillingDocumentCounter;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Assegna numeri progressivi, senza buchi, per tenant+anno (si azzera il
 * 1° gennaio). DEVE essere chiamato dentro una DB::transaction() già
 * aperta dal chiamante — qui dentro si limita a lockare e incrementare la
 * riga contatore, non apre una propria transazione.
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
    public static function next(Tenant $tenant, int $year): int
    {
        $counter = self::lockCounterRow($tenant, $year);

        if (! $counter) {
            try {
                $counter = new BillingDocumentCounter(['year' => $year, 'next_number' => 1]);
                $counter->tenant_id = $tenant->id;
                $counter->save();
            } catch (UniqueConstraintViolationException) {
                // Un'altra transazione concorrente l'ha creata per prima
                // (stessa finestra di corsa critica del primo documento
                // dell'anno) — lockiamo la sua riga e procediamo da lì.
                $counter = self::lockCounterRow($tenant, $year);
            }
        }

        $number = $counter->next_number;
        $counter->next_number = $number + 1;
        $counter->save();

        return $number;
    }

    private static function lockCounterRow(Tenant $tenant, int $year): ?BillingDocumentCounter
    {
        return BillingDocumentCounter::query()
            ->where('tenant_id', $tenant->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();
    }
}
