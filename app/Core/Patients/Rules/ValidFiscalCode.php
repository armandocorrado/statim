<?php

namespace App\Core\Patients\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Verifica che un codice fiscale italiano sia strutturalmente valido: formato
 * a 16 caratteri (tollerante all'omocodia) + carattere di controllo corretto.
 *
 * Non verifica che il codice corrisponda davvero a nome/cognome/data di
 * nascita/sesso/luogo di nascita del paziente — quel controllo richiederebbe
 * una tabella dei codici catastali dei comuni ed è stato deliberatamente
 * rimandato (vedi CLAUDE.md).
 */
class ValidFiscalCode implements ValidationRule
{
    private const FORMAT = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';

    /** Tabella di conversione ufficiale per i caratteri in posizione dispari (1-indexed). */
    private const ODD_VALUES = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Il :attribute non è valido.');

            return;
        }

        $code = strtoupper($value);

        if (! preg_match(self::FORMAT, $code)) {
            $fail('Il :attribute non ha un formato valido.');

            return;
        }

        if (self::checkDigit(substr($code, 0, 15)) !== $code[15]) {
            $fail('Il :attribute non è valido (carattere di controllo errato).');
        }
    }

    private static function checkDigit(string $first15): string
    {
        $sum = 0;

        foreach (str_split($first15) as $position => $char) {
            // $position is 0-indexed; the official algorithm counts
            // 1-indexed odd positions, i.e. every even $position here.
            $sum += $position % 2 === 0
                ? self::ODD_VALUES[$char]
                : (ctype_digit($char) ? (int) $char : ord($char) - ord('A'));
        }

        return chr(65 + ($sum % 26));
    }
}
