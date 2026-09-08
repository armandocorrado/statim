<?php

namespace App\Core\Billing\Contracts;

/**
 * Esito condiviso dalle tre porte esterne (SdI, Sistema TS, conservazione)
 * — stessa forma per tutte, così il chiamante non deve conoscere i
 * dettagli di ciascun canale.
 */
final readonly class FiscalSubmissionResult
{
    public function __construct(
        public bool $success,
        public ?string $reference = null,
        public ?string $message = null,
    ) {}
}
