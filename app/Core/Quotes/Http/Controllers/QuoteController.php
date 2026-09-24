<?php

namespace App\Core\Quotes\Http\Controllers;

use App\Core\Billing\Enums\BillingDocumentStatus;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Billing\Support\BillingDocumentRecipientSnapshot;
use App\Core\Billing\Support\BillingDocumentTotalsCalculator;
use App\Core\Billing\Support\VatExemptionReasons;
use App\Core\Quotes\Enums\QuoteStatus;
use App\Core\Quotes\Http\Requests\UpdateQuoteRequest;
use App\Core\Quotes\Http\Requests\UpdateQuoteStatusRequest;
use App\Core\Quotes\Models\Quote;
use App\Core\Quotes\Models\ServiceCatalogItem;
use App\Core\Quotes\Support\QuoteTransitions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    /**
     * Tasso di accettazione = accettati (Accepted+InProgress+Completed) /
     * emessi (ogni stato diverso da Draft — una bozza non è mai stata
     * proposta al paziente, non entra nel denominatore). Corrisponde
     * letteralmente a "preventivi accettati / emessi".
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Quote::class);

        $quotes = Quote::query()
            ->with('patient:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate(15);

        $issued = Quote::query()->where('status', '!=', QuoteStatus::Draft->value)->count();
        $accepted = Quote::query()->whereIn('status', array_column(QuoteStatus::acceptedStatuses(), 'value'))->count();

        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes,
            'acceptanceRate' => [
                'issued' => $issued,
                'accepted' => $accepted,
                'rate' => $issued > 0 ? round($accepted / $issued * 100, 1) : null,
            ],
        ]);
    }

    public function show(Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $user = request()->user();

        return Inertia::render('Quotes/Show', [
            'quote' => $quote->load(['patient:id,first_name,last_name', 'lines', 'creator:id,name']),
            // Tre flag distinti, mai sovrapposti: canManage (modifica
            // prezzi) può valere per un dentista abilitato senza che
            // questo implichi canIssue/canDelete, riservati a
            // treatment_plans.administer — vedi QuotePolicy.
            'canManage' => $user->can('update', $quote),
            'canIssue' => $user->can('issue', $quote),
            'canDelete' => $user->can('delete', $quote),
            'canTransition' => $user->can('transition', $quote),
            'allowedNextStatuses' => QuoteTransitions::allowedNextValues($quote->status),
            'canGenerateBillingDocument' => $user->can('generateBillingDocument', $quote),
            'billingDocuments' => $quote->billingDocuments()
                ->orderByDesc('created_at')
                ->get(['id', 'status', 'document_number', 'document_year', 'issued_at', 'total_amount', 'created_at']),
        ]);
    }

    public function edit(Quote $quote): Response
    {
        $this->authorize('update', $quote);

        return Inertia::render('Quotes/Edit', [
            'quote' => $quote->load(['patient:id,first_name,last_name', 'lines']),
            'serviceCatalogItems' => ServiceCatalogItem::where('is_active', true)->orderBy('name')->get(),
            'vatExemptionReasons' => VatExemptionReasons::commonReasons(),
        ]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $this->replaceLines($quote, $request->validated()['lines']);

        return to_route('quotes.show', $quote)->with('success', 'Preventivo aggiornato.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        $quote->delete();

        return to_route('quotes.index')->with('success', 'Bozza eliminata.');
    }

    /**
     * L'unica transizione Draft -> Issued. I totali vengono calcolati e
     * congelati qui, mai prima — stesso principio di BillingDocument. Lo
     * sconto per riga si applica prima di passare i dati al calculator
     * già usato da Billing: quella classe resta quella, senza doverle
     * insegnare a conoscere gli sconti.
     */
    public function issue(Quote $quote): RedirectResponse
    {
        $this->authorize('issue', $quote);

        $totals = BillingDocumentTotalsCalculator::calculate($quote->lines->map(fn ($line) => [
            'quantity' => $line->quantity,
            'unit_price' => $line->discountedUnitPrice(),
            'vat_rate' => $line->vat_rate,
        ]));

        $quote->status = QuoteStatus::Issued;
        $quote->issued_at = now();
        $quote->total_taxable = $totals['taxable'];
        $quote->total_vat = $totals['vat'];
        $quote->total_amount = $totals['total'];
        $quote->save();

        return to_route('quotes.show', $quote)->with('success', 'Preventivo emesso.');
    }

    /**
     * Genera un documento fiscale (bozza) dal preventivo — stesso pattern
     * di DentalTreatmentPlanController::generateQuote() un livello più su
     * (piano di cura → preventivo). 1:N deliberato: un preventivo può
     * generare più documenti nel tempo (es. fatturazione a fasi/acconti),
     * nessun vincolo di unicità su source_quote_id.
     *
     * Lo sconto per riga si "congela" nel prezzo unitario finale — la
     * fattura non ha un campo sconto separato (BillingDocumentLine non lo
     * prevede), a differenza del preventivo dove il negoziato resta
     * visibile come percentuale a parte.
     */
    public function generateBillingDocument(Quote $quote): RedirectResponse
    {
        $this->authorize('generateBillingDocument', $quote);

        $document = new BillingDocument(['patient_id' => $quote->patient_id]);
        $document->source_quote_id = $quote->id;
        $document->status = BillingDocumentStatus::Draft;
        $document->created_by = request()->user()->id;
        BillingDocumentRecipientSnapshot::apply($document, $quote->patient_id, null);
        $document->save();

        foreach ($quote->lines as $index => $line) {
            $newLine = $document->lines()->make([
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->discountedUnitPrice(),
                'vat_rate' => $line->vat_rate,
                'vat_exemption_reason' => $line->vat_exemption_reason,
                'sort_order' => $index,
            ]);
            $newLine->line_total = round((float) $line->quantity * $line->discountedUnitPrice(), 2);
            $newLine->save();
        }

        return to_route('billing.show', $document)->with('success', 'Documento fiscale generato dal preventivo.');
    }

    public function updateStatus(UpdateQuoteStatusRequest $request, Quote $quote): RedirectResponse
    {
        $target = QuoteStatus::from($request->validated()['status']);

        $quote->status = $target;

        if (in_array($target, [QuoteStatus::Accepted, QuoteStatus::Rejected], true)) {
            $quote->responded_at = now();
        }

        $quote->save();

        return to_route('quotes.show', $quote)->with('success', 'Stato aggiornato.');
    }

    /**
     * @param  list<array{service_catalog_item_id: ?string, description: string, quantity: float|string, unit_price: float|string, discount_percent: float|string|null, vat_rate: float|string|null, vat_exemption_reason: ?string}>  $lines
     */
    private function replaceLines(Quote $quote, array $lines): void
    {
        $quote->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $newLine = $quote->lines()->make([
                'service_catalog_item_id' => $line['service_catalog_item_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_percent' => $line['discount_percent'] ?? null,
                'vat_rate' => $line['vat_rate'] ?? null,
                'vat_exemption_reason' => $line['vat_exemption_reason'] ?? null,
                'sort_order' => $index,
            ]);
            // line_total non è mass-assignable: calcolato qui, mai
            // arrivato da un form (stesso principio di BillingDocumentLine).
            $discount = ($line['discount_percent'] ?? null) !== null ? (float) $line['discount_percent'] : 0.0;
            $discountedUnitPrice = round((float) $line['unit_price'] * (1 - $discount / 100), 2);
            $newLine->line_total = round((float) $line['quantity'] * $discountedUnitPrice, 2);
            $newLine->save();
        }
    }
}
