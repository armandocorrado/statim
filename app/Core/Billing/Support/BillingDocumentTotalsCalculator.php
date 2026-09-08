<?php

namespace App\Core\Billing\Support;

use Illuminate\Support\Collection;

/**
 * Calcola imponibile/IVA/totale da un insieme di righe. Isolato in una
 * classe dedicata (non inline nel controller) perché all'emissione questi
 * valori vengono congelati sul documento — un bug qui, se non testato a
 * parte, corromperebbe silenziosamente ogni fattura emessa da quel
 * momento in poi.
 *
 * @param  Collection<int, array{quantity: float|string, unit_price: float|string, vat_rate: float|string|null}>  $lines
 * @return array{taxable: float, vat: float, total: float}
 */
class BillingDocumentTotalsCalculator
{
    public static function calculate(Collection $lines): array
    {
        $taxable = 0.0;
        $vat = 0.0;

        foreach ($lines as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $rate = $line['vat_rate'] ?? null;

            $taxable += $lineTotal;

            if ($rate !== null && (float) $rate > 0) {
                $vat += round($lineTotal * ((float) $rate / 100), 2);
            }
        }

        return [
            'taxable' => round($taxable, 2),
            'vat' => round($vat, 2),
            'total' => round($taxable + $vat, 2),
        ];
    }
}
