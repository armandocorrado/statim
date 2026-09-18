<?php

namespace App\Core\Billing\Support;

/**
 * Causali di esenzione IVA più comuni per prestazioni sanitarie — SOLO un
 * aiuto per la UI (menu a tendina con "Altro" per il testo libero), non
 * un vincolo di schema: `vat_exemption_reason` resta una colonna testo
 * libero su `BillingDocumentLine`/`QuoteLine` (vedi CLAUDE.md, sezione
 * "Fatturazione") — non è certo che l'elenco applicabile a ogni caso sia
 * solo questo, quindi nessuna validazione server-side lo impone.
 *
 * **Da validare con un commercialista prima di un uso reale** — elenco
 * indicativo, non una tassonomia fiscale certificata.
 */
class VatExemptionReasons
{
    /**
     * @return array<string, string> valore congelato sulla riga => etichetta mostrata in UI
     */
    public static function commonReasons(): array
    {
        return [
            'art. 10 n. 18 DPR 633/72' => 'Art. 10 n. 18 DPR 633/72 — prestazioni sanitarie di diagnosi, cura e riabilitazione',
            'art. 10 n. 19 DPR 633/72' => 'Art. 10 n. 19 DPR 633/72 — prestazioni sanitarie rese da strutture (case di cura, poliambulatori)',
            'art. 10 n. 27-ter DPR 633/72' => 'Art. 10 n. 27-ter DPR 633/72 — prestazioni socio-sanitarie e assistenziali',
        ];
    }
}
