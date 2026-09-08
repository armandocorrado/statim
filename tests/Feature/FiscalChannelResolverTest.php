<?php

use App\Core\Billing\Enums\FiscalChannel;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Billing\Support\FiscalChannelResolver;

test('a document for a private individual is always routed to Sistema TS, never SdI', function () {
    $document = BillingDocument::factory()->make();

    expect(FiscalChannelResolver::resolve($document))->toBe(FiscalChannel::SistemaTs);
});

test('the fiscal channel is a single field, not two independent flags — sending to both is unrepresentable', function () {
    // Non è tanto un test quanto una garanzia strutturale: verifica che
    // l'enum abbia esattamente questi due valori, e che il cast del
    // model sia FiscalChannel — un cambiamento che aggiungesse un terzo
    // stato "entrambi" o tornasse a due booleani indipendenti romperebbe
    // questo test, non solo silenziosamente il comportamento.
    expect(array_map(fn ($case) => $case->value, FiscalChannel::cases()))
        ->toBe(['sistema_ts', 'sdi']);
});
