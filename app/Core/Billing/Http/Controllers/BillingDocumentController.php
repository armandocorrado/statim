<?php

namespace App\Core\Billing\Http\Controllers;

use App\Core\Billing\Contracts\DigitalPreservationGateway;
use App\Core\Billing\Contracts\ElectronicInvoiceGateway;
use App\Core\Billing\Contracts\HealthExpenseReportingGateway;
use App\Core\Billing\Enums\BillingDocumentStatus;
use App\Core\Billing\Enums\FiscalChannel;
use App\Core\Billing\Http\Requests\StoreBillingDocumentRequest;
use App\Core\Billing\Http\Requests\UpdateBillingDocumentRequest;
use App\Core\Billing\Models\BillingDocument;
use App\Core\Billing\Support\BillingDocumentNumberer;
use App\Core\Billing\Support\BillingDocumentRecipientSnapshot;
use App\Core\Billing\Support\BillingDocumentTotalsCalculator;
use App\Core\Billing\Support\FiscalChannelResolver;
use App\Core\Billing\Support\VatExemptionReasons;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillingDocumentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', BillingDocument::class);

        $documents = BillingDocument::query()
            ->with('patient:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate(15);

        return Inertia::render('Billing/Index', [
            'documents' => $documents,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', BillingDocument::class);

        return Inertia::render('Billing/Create', [
            'vatExemptionReasons' => VatExemptionReasons::commonReasons(),
        ]);
    }

    public function store(StoreBillingDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $document = new BillingDocument([
            'patient_id' => $data['patient_id'],
            'recipient_patient_id' => $data['recipient_patient_id'] ?? null,
        ]);
        $document->status = BillingDocumentStatus::Draft;
        $document->created_by = $request->user()->id;
        BillingDocumentRecipientSnapshot::apply($document, $data['patient_id'], $data['recipient_patient_id'] ?? null);
        $document->save();

        $this->replaceLines($document, $data['lines']);

        return to_route('billing.show', $document)->with('success', 'Bozza creata.');
    }

    public function show(BillingDocument $document): Response
    {
        $this->authorize('view', $document);

        return Inertia::render('Billing/Show', [
            'document' => $document->load([
                'patient:id,first_name,last_name',
                'recipient:id,first_name,last_name',
                'lines',
                'sourceQuote:id,issued_at',
            ]),
        ]);
    }

    public function edit(BillingDocument $document): Response
    {
        $this->authorize('update', $document);

        return Inertia::render('Billing/Edit', [
            'document' => $document->load(['patient:id,first_name,last_name', 'recipient:id,first_name,last_name', 'lines']),
            'vatExemptionReasons' => VatExemptionReasons::commonReasons(),
        ]);
    }

    public function update(UpdateBillingDocumentRequest $request, BillingDocument $document): RedirectResponse
    {
        $data = $request->validated();

        $document->patient_id = $data['patient_id'];
        $document->recipient_patient_id = $data['recipient_patient_id'] ?? null;
        BillingDocumentRecipientSnapshot::apply($document, $data['patient_id'], $data['recipient_patient_id'] ?? null);
        $document->save();

        $this->replaceLines($document, $data['lines']);

        return to_route('billing.show', $document)->with('success', 'Bozza aggiornata.');
    }

    public function destroy(BillingDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $document->delete();

        return to_route('billing.index')->with('success', 'Bozza eliminata.');
    }

    /**
     * L'unica transizione Draft -> Issued. Numero e totali vengono
     * assegnati/congelati qui, mai prima — vedi BillingDocument e
     * BillingDocumentNumberer. Il canale fiscale viene risolto una volta
     * sola (FiscalChannelResolver) e SOLO il gateway corrispondente viene
     * chiamato — mai entrambi, vedi FiscalChannel.
     */
    public function issue(
        BillingDocument $document,
        ElectronicInvoiceGateway $sdiGateway,
        HealthExpenseReportingGateway $tsGateway,
        DigitalPreservationGateway $preservationGateway,
    ): RedirectResponse {
        $this->authorize('issue', $document);

        DB::transaction(function () use ($document, $sdiGateway, $tsGateway, $preservationGateway) {
            $tenant = $document->tenant;
            $year = (int) now()->format('Y');

            $totals = BillingDocumentTotalsCalculator::calculate($document->lines->map(fn ($line) => [
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate' => $line->vat_rate,
            ]));

            $document->document_number = BillingDocumentNumberer::next($tenant, $year);
            $document->document_year = $year;
            $document->issued_at = now();
            $document->total_taxable = $totals['taxable'];
            $document->total_vat = $totals['vat'];
            $document->total_amount = $totals['total'];
            $document->fiscal_channel = FiscalChannelResolver::resolve($document);
            $document->status = BillingDocumentStatus::Issued;

            $result = $document->fiscal_channel === FiscalChannel::SistemaTs
                ? $tsGateway->report($document)
                : $sdiGateway->send($document);

            $document->external_reference = $result->reference;
            $document->save();

            $preservationGateway->preserve($document);
        });

        return to_route('billing.show', $document)->with('success', 'Documento emesso.');
    }

    /**
     * @param  list<array{description: string, quantity: float|string, unit_price: float|string, vat_rate: float|string|null, vat_exemption_reason: ?string}>  $lines
     */
    private function replaceLines(BillingDocument $document, array $lines): void
    {
        $document->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            // line_total non è mass-assignable (Fillable la esclude
            // apposta — non deve poter arrivare da un form web bypassando
            // il calcolo quantità*prezzo): impostata a parte.
            $newLine = $document->lines()->make([
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'vat_rate' => $line['vat_rate'] ?? null,
                'vat_exemption_reason' => $line['vat_exemption_reason'] ?? null,
                'sort_order' => $index,
            ]);
            $newLine->line_total = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $newLine->save();
        }
    }
}
