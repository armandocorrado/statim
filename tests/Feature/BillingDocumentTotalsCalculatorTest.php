<?php

use App\Core\Billing\Support\BillingDocumentTotalsCalculator;
use Illuminate\Support\Collection;

test('a fully vat-exempt document has zero vat', function () {
    $totals = BillingDocumentTotalsCalculator::calculate(new Collection([
        ['quantity' => 1, 'unit_price' => 80, 'vat_rate' => null],
        ['quantity' => 2, 'unit_price' => 50, 'vat_rate' => 0],
    ]));

    expect($totals)->toBe(['taxable' => 180.0, 'vat' => 0.0, 'total' => 180.0]);
});

test('mixed exempt and taxable lines compute vat only on the taxable ones', function () {
    $totals = BillingDocumentTotalsCalculator::calculate(new Collection([
        ['quantity' => 1, 'unit_price' => 100, 'vat_rate' => null], // prestazione esente
        ['quantity' => 1, 'unit_price' => 50, 'vat_rate' => 22], // prodotto imponibile
    ]));

    expect($totals)->toBe(['taxable' => 150.0, 'vat' => 11.0, 'total' => 161.0]);
});

test('quantity multiplies the line amount', function () {
    $totals = BillingDocumentTotalsCalculator::calculate(new Collection([
        ['quantity' => 3, 'unit_price' => 25.5, 'vat_rate' => null],
    ]));

    expect($totals)->toBe(['taxable' => 76.5, 'vat' => 0.0, 'total' => 76.5]);
});
