<?php

namespace App\Core\Billing\Enums;

/**
 * Canale fiscale di un documento — MAI entrambi. Un documento le cui
 * spese sono riportate al Sistema TS (Tessera Sanitaria, per il 730
 * precompilato) non deve MAI essere inviato anche allo SdI verso il
 * privato: è un divieto di legge a tutela della riservatezza dei dati
 * sanitari, non un dettaglio tecnico. Il fatto che questo sia un unico
 * campo enum — non due booleani indipendenti (`sent_to_ts`,
 * `sent_to_sdi`) — rende la violazione "inviato a entrambi"
 * strutturalmente irrappresentabile nello schema, non solo vietata da un
 * controllo runtime che si potrebbe dimenticare. Vedi
 * App\Core\Billing\Support\FiscalChannelResolver.
 */
enum FiscalChannel: string
{
    case SistemaTs = 'sistema_ts';
    case Sdi = 'sdi';

    public function label(): string
    {
        return match ($this) {
            self::SistemaTs => 'Sistema Tessera Sanitaria',
            self::Sdi => 'Sistema di Interscambio (fattura elettronica)',
        };
    }
}
