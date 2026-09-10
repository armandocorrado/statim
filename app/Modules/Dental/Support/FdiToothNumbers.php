<?php

namespace App\Modules\Dental\Support;

/**
 * Notazione FDI/ISO 3950 a due cifre (quadrante + posizione) — lo standard
 * odontoiatrico italiano/europeo, non l'"Universal" 1-32 americano.
 *
 * Permanenti: quadranti 1-4 × posizioni 1-8 (11-18, 21-28, 31-38, 41-48).
 * Decidui: quadranti 5-8 × posizioni 1-5, stessa geometria dei permanenti
 * ma senza premolari/terzo molare (51-55, 61-65, 71-75, 81-85) — servono
 * fin da subito perché uno studio tratta bambini, inclusa la dentizione
 * mista (6-12 anni: permanenti e decidui coesistono nello stesso paziente).
 * Non è una tabella di dato di tenant: è uno standard fisso.
 */
class FdiToothNumbers
{
    /**
     * @return list<string>
     */
    public static function permanent(): array
    {
        return self::forQuadrants([1, 2, 3, 4], 8);
    }

    /**
     * @return list<string>
     */
    public static function deciduous(): array
    {
        return self::forQuadrants([5, 6, 7, 8], 5);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::permanent(), ...self::deciduous()];
    }

    public static function isValid(string $toothNumber): bool
    {
        return in_array($toothNumber, self::all(), true);
    }

    /**
     * @param  list<int>  $quadrants
     * @return list<string>
     */
    private static function forQuadrants(array $quadrants, int $maxPosition): array
    {
        $numbers = [];

        foreach ($quadrants as $quadrant) {
            for ($position = 1; $position <= $maxPosition; $position++) {
                $numbers[] = "{$quadrant}{$position}";
            }
        }

        return $numbers;
    }
}
